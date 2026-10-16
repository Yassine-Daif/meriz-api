<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\AuthenticateUser;
use App\Actions\Auth\SendPasswordResetLink;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'ancien-motdepasse';

    private const NEW = 'nouveau-motdepasse';

    private const FRONT = 'https://app.meriz.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'frontend.url' => self::FRONT,
            'frontend.password_reset_path' => '/reinitialiser-mot-de-passe',
        ]);
    }

    private function account(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'alice@example.com',
            'password' => self::OLD,
        ], $attributes));
    }

    /**
     * Le jeton en clair, tel qu'il part dans l'email.
     */
    private function tokenFor(User $user): string
    {
        return Password::createToken($user);
    }

    public function test_a_known_address_receives_a_link(): void
    {
        Notification::fake();
        $alice = $this->account();

        $this->postJson('/api/auth/password/forgot', ['email' => 'alice@example.com'])
            ->assertOk()
            ->assertExactJson(['message' => SendPasswordResetLink::GENERIC_MESSAGE]);

        Notification::assertSentTo($alice, ResetPasswordLink::class);
    }

    public function test_an_unknown_address_answers_exactly_the_same(): void
    {
        Notification::fake();
        $this->account();

        $known = $this->postJson('/api/auth/password/forgot', ['email' => 'alice@example.com']);
        $unknown = $this->postJson('/api/auth/password/forgot', ['email' => 'personne@example.com']);

        // Même code, même corps, octet pour octet : rien ne distingue les
        // deux réponses.
        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame($known->getContent(), $unknown->getContent());

        Notification::assertCount(1);
    }

    public function test_a_deleted_account_answers_the_same_and_receives_nothing(): void
    {
        Notification::fake();
        $alice = $this->account();
        $alice->forceFill(['anonymized_at' => now()])->save();

        $this->postJson('/api/auth/password/forgot', ['email' => 'alice@example.com'])
            ->assertOk()
            ->assertExactJson(['message' => SendPasswordResetLink::GENERIC_MESSAGE]);

        Notification::assertNothingSent();
    }

    public function test_the_link_points_to_the_front_not_the_api(): void
    {
        Notification::fake();
        $alice = $this->account();

        $this->postJson('/api/auth/password/forgot', ['email' => 'alice@example.com'])->assertOk();

        Notification::assertSentTo($alice, ResetPasswordLink::class, function (ResetPasswordLink $notification) use ($alice) {
            $url = $notification->toMail($alice)->actionUrl;

            $this->assertStringStartsWith(self::FRONT.'/reinitialiser-mot-de-passe?', $url);
            $this->assertStringContainsString('token=', $url);
            $this->assertStringContainsString('email=alice%40example.com', $url);
            $this->assertStringNotContainsString('/api/', $url);

            return true;
        });
    }

    public function test_a_valid_token_changes_the_password(): void
    {
        Event::fake([PasswordReset::class]);
        $alice = $this->account();

        $this->postJson('/api/auth/password/reset', [
            'token' => $this->tokenFor($alice),
            'email' => 'alice@example.com',
            'password' => self::NEW,
        ])->assertOk()->assertJsonPath('message', 'Ton mot de passe a été réinitialisé.');

        $alice->refresh();
        $this->assertTrue(Hash::check(self::NEW, $alice->password));
        $this->assertFalse(Hash::check(self::OLD, $alice->password));

        Event::assertDispatched(PasswordReset::class);
    }

    public function test_the_old_password_no_longer_connects_and_the_new_one_does(): void
    {
        $alice = $this->account();

        $this->postJson('/api/auth/password/reset', [
            'token' => $this->tokenFor($alice),
            'email' => 'alice@example.com',
            'password' => self::NEW,
        ])->assertOk();

        $this->postJson('/api/auth/login', ['email' => 'alice@example.com', 'password' => self::OLD])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', AuthenticateUser::FAILED_MESSAGE);

        $this->postJson('/api/auth/login', ['email' => 'alice@example.com', 'password' => self::NEW])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');
    }

    public function test_every_existing_token_is_revoked(): void
    {
        // Le point de la phase : un compte volé se reprend ici, donc la
        // session du voleur doit tomber.
        $alice = $this->account();
        $stolen = $alice->createToken('appareil du voleur')->plainTextToken;

        $this->withToken($stolen)->getJson('/api/me')->assertOk();

        $this->postJson('/api/auth/password/reset', [
            'token' => $this->tokenFor($alice),
            'email' => 'alice@example.com',
            'password' => self::NEW,
        ])->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($stolen)->getJson('/api/me')->assertUnauthorized();
        $this->assertSame(0, $alice->tokens()->count());
    }

    public function test_the_response_carries_no_api_token(): void
    {
        $alice = $this->account();

        $response = $this->postJson('/api/auth/password/reset', [
            'token' => $this->tokenFor($alice),
            'email' => 'alice@example.com',
            'password' => self::NEW,
        ])->assertOk();

        $response->assertJsonMissingPath('data')
            ->assertJsonMissingPath('token');
        $this->assertStringNotContainsString('meriz_', $response->getContent());
    }

    public function test_a_forged_token_is_refused(): void
    {
        $alice = $this->account();

        $this->postJson('/api/auth/password/reset', [
            'token' => 'jeton-invente-de-toutes-pieces',
            'email' => 'alice@example.com',
            'password' => self::NEW,
        ])->assertStatus(422)
            ->assertJsonPath('errors.token.0', 'Ce lien de réinitialisation n\'est plus valable.');

        $this->assertTrue(Hash::check(self::OLD, $alice->fresh()->password));
    }

    public function test_a_token_of_another_address_is_refused(): void
    {
        $alice = $this->account();
        $bob = $this->account(['email' => 'bob@example.com']);

        $this->postJson('/api/auth/password/reset', [
            'token' => $this->tokenFor($bob),
            'email' => 'alice@example.com',
            'password' => self::NEW,
        ])->assertStatus(422);

        $this->assertTrue(Hash::check(self::OLD, $alice->fresh()->password));
        $this->assertTrue(Hash::check(self::OLD, $bob->fresh()->password));
    }

    public function test_an_expired_token_is_refused(): void
    {
        $alice = $this->account();
        $token = $this->tokenFor($alice);

        // L'expiration est réglée à 60 minutes dans config/auth.php.
        $this->travel(61)->minutes();

        $this->postJson('/api/auth/password/reset', [
            'token' => $token,
            'email' => 'alice@example.com',
            'password' => self::NEW,
        ])->assertStatus(422);

        $this->assertTrue(Hash::check(self::OLD, $alice->fresh()->password));
    }

    public function test_a_token_serves_only_once(): void
    {
        $alice = $this->account();
        $token = $this->tokenFor($alice);

        $this->postJson('/api/auth/password/reset', [
            'token' => $token,
            'email' => 'alice@example.com',
            'password' => self::NEW,
        ])->assertOk();

        $this->postJson('/api/auth/password/reset', [
            'token' => $token,
            'email' => 'alice@example.com',
            'password' => 'encore-un-autre-motdepasse',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check(self::NEW, $alice->fresh()->password));
    }

    public function test_a_deleted_account_cannot_be_taken_back(): void
    {
        $alice = $this->account();
        $token = $this->tokenFor($alice);
        $alice->forceFill(['anonymized_at' => now()])->save();

        $this->postJson('/api/auth/password/reset', [
            'token' => $token,
            'email' => 'alice@example.com',
            'password' => self::NEW,
        ])->assertStatus(422);

        $this->assertTrue(Hash::check(self::OLD, $alice->fresh()->password));
    }

    public function test_resetting_verifies_the_address(): void
    {
        // Suivre ce lien prouve la même chose que le lien de vérification :
        // on lit cette boîte.
        $alice = $this->account(['email_verified_at' => null]);

        $this->postJson('/api/auth/password/reset', [
            'token' => $this->tokenFor($alice),
            'email' => 'alice@example.com',
            'password' => self::NEW,
        ])->assertOk();

        $this->assertNotNull($alice->fresh()->email_verified_at);
    }

    public function test_an_academic_account_can_become_teacher_after_resetting(): void
    {
        // Les deux phases reliées : l'adresse scolaire non vérifiée bloquait
        // le mode prof, la réinitialisation vient de la vérifier.
        $alice = User::factory()->academic()->unverified()->create([
            'email' => 'claire@ac-lyon.fr',
            'password' => self::OLD,
        ]);

        $this->postJson('/api/auth/password/reset', [
            'token' => $this->tokenFor($alice),
            'email' => 'claire@ac-lyon.fr',
            'password' => self::NEW,
        ])->assertOk();

        $token = $this->postJson('/api/auth/login', [
            'email' => 'claire@ac-lyon.fr',
            'password' => self::NEW,
        ])->assertOk()->json('data.token');

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->postJson('/api/me/teacher-role')
            ->assertOk()
            ->assertJsonPath('data.role', UserRole::Teacher->value);
    }

    public function test_the_request_is_validated(): void
    {
        $this->postJson('/api/auth/password/forgot', [])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/auth/password/forgot', ['email' => 'pas-une-adresse'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/auth/password/reset', [])->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'email', 'password']);

        $alice = $this->account();

        // Mêmes règles qu'à l'inscription : au moins 8 caractères.
        $this->postJson('/api/auth/password/reset', [
            'token' => $this->tokenFor($alice),
            'email' => 'alice@example.com',
            'password' => 'court',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_asking_for_a_link_is_rate_limited(): void
    {
        Notification::fake();
        $this->account();

        // 3 par minute et par adresse.
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/password/forgot', ['email' => 'alice@example.com'])->assertOk();
        }

        $this->postJson('/api/auth/password/forgot', ['email' => 'alice@example.com'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Trop de requêtes, réessaie dans un instant.');
    }

    public function test_resetting_is_rate_limited(): void
    {
        $this->account();

        // 6 par minute et par IP.
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/password/reset', [
                'token' => 'jeton-invalide',
                'email' => 'alice@example.com',
                'password' => self::NEW,
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/password/reset', [
            'token' => 'jeton-invalide',
            'email' => 'alice@example.com',
            'password' => self::NEW,
        ])->assertStatus(429);
    }
}
