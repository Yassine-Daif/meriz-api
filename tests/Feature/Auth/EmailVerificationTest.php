<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Models\User;
use App\Notifications\VerifyEmailAddress;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const FRONT = 'https://app.meriz.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'frontend.url' => self::FRONT,
            'frontend.email_verified_path' => '/email-verifie',
            'auth.verification.expire' => 60,
        ]);
    }

    private function landing(string $result): string
    {
        return self::FRONT.'/email-verifie?verification='.$result;
    }

    private function linkFor(User $user, ?string $email = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($email ?? $user->email),
        ]);
    }

    public function test_registration_sends_a_verification_link(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Martin',
            'first_name' => 'Alice',
            'email' => 'alice@gmail.com',
            'password' => 'motdepasse-solide',
        ])->assertCreated()
            ->assertJsonPath('data.user.email_verified_at', null);

        $user = User::firstWhere('email', 'alice@gmail.com');

        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailAddress::class);
    }

    public function test_a_valid_link_verifies_the_account(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();

        $this->get($this->linkFor($user))
            ->assertRedirect($this->landing('success'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    public function test_an_expired_link_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $link = $this->linkFor($user);

        $this->travel(61)->minutes();

        $this->get($link)->assertRedirect($this->landing('expired'));

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_tampered_link_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();

        // Signature valable, mais pour un autre compte : l'identifiant fait
        // partie de ce qui est signé.
        $link = str_replace('/verify/'.$user->id.'/', '/verify/'.$other->id.'/', $this->linkFor($user));

        $this->get($link)->assertRedirect($this->landing('invalid'));

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_a_link_stretched_in_time_is_refused(): void
    {
        $user = User::factory()->unverified()->create();

        // Rallonger la validité à la main casse la signature : l'expiration
        // fait partie de ce qui est signé.
        $link = preg_replace(
            '/expires=\d+/',
            'expires='.now()->addYear()->getTimestamp(),
            $this->linkFor($user),
        );

        $this->get($link)->assertRedirect($this->landing('invalid'));

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_link_without_signature_is_refused(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get('/api/auth/email/verify/'.$user->id.'/'.sha1($user->email))
            ->assertRedirect($this->landing('invalid'));

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_hash_of_another_address_is_refused(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->linkFor($user, 'autre@example.com'))
            ->assertRedirect($this->landing('invalid'));

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_an_unknown_account_answers_exactly_like_a_bad_link(): void
    {
        // Même réponse qu'un lien falsifié : le lien ne dit pas si un
        // compte existe.
        $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => 999999,
            'hash' => sha1('inconnu@example.com'),
        ]);

        $this->get($link)->assertRedirect($this->landing('invalid'));
    }

    public function test_a_link_already_used_verifies_nothing_again(): void
    {
        $user = User::factory()->unverified()->create();
        $link = $this->linkFor($user);

        $this->get($link)->assertRedirect($this->landing('success'));
        $verifiedAt = $user->fresh()->email_verified_at;

        $this->travel(5)->minutes();

        Event::fake([Verified::class]);

        $this->get($link)->assertRedirect($this->landing('already'));

        $this->assertTrue($verifiedAt->equalTo($user->fresh()->email_verified_at));
        Event::assertNotDispatched(Verified::class);
    }

    public function test_the_landing_address_comes_from_the_configuration(): void
    {
        config([
            'frontend.url' => 'https://autre.meriz.test/',
            'frontend.email_verified_path' => 'retour-email',
        ]);

        $user = User::factory()->unverified()->create();

        $this->get($this->linkFor($user))
            ->assertRedirect('https://autre.meriz.test/retour-email?verification=success');
    }

    public function test_resending_sends_a_new_link(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/verification-notification')
            ->assertStatus(202)
            ->assertJsonPath('message', EmailVerificationController::SENT_MESSAGE);

        Notification::assertSentTo($user, VerifyEmailAddress::class);
    }

    public function test_resending_sends_nothing_when_the_account_is_verified(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', EmailVerificationController::ALREADY_MESSAGE);

        Notification::assertNothingSent();
    }

    public function test_resending_is_rate_limited(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/verification-notification')->assertStatus(202);
        $this->postJson('/api/auth/email/verification-notification')->assertStatus(202);
        $this->postJson('/api/auth/email/verification-notification')->assertStatus(429);

        Notification::assertSentToTimes($user, VerifyEmailAddress::class, 2);
    }

    public function test_resending_requires_a_token(): void
    {
        $this->postJson('/api/auth/email/verification-notification')->assertUnauthorized();
    }
}
