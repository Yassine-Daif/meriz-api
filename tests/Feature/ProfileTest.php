<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_a_first_name(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Martin',
            'email' => 'alice@gmail.com',
            'password' => 'motdepasse-solide',
        ])->assertUnprocessable()->assertJsonValidationErrors(['first_name']);

        $this->postJson('/api/auth/register', [
            'name' => 'Martin',
            'first_name' => 'Alice',
            'email' => 'alice@gmail.com',
            'password' => 'motdepasse-solide',
        ])->assertCreated()
            ->assertJsonPath('data.user.name', 'Martin')
            ->assertJsonPath('data.user.first_name', 'Alice');
    }

    public function test_sharing_is_off_by_default(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertJsonPath('data.bio_shared', false)
            ->assertJsonPath('data.contact_shared', false);
    }

    public function test_user_updates_their_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/me', [
            'first_name' => 'Léa',
            'bio' => 'J\'aime les maths.',
            'bio_shared' => true,
            'contact' => 'lea.contact@exemple.fr',
            'contact_shared' => false,
        ])->assertOk()
            ->assertJsonPath('data.first_name', 'Léa')
            ->assertJsonPath('data.bio', 'J\'aime les maths.')
            ->assertJsonPath('data.bio_shared', true)
            ->assertJsonPath('data.contact', 'lea.contact@exemple.fr')
            ->assertJsonPath('data.contact_shared', false);

        $fresh = $user->fresh();
        $this->assertSame('J\'aime les maths.', $fresh->sharedBio());
        $this->assertNull($fresh->sharedContact());
    }

    public function test_profile_fields_can_be_cleared(): void
    {
        $user = User::factory()->withSharedProfile()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/me', ['bio' => null, 'contact' => ''])->assertOk();

        $this->assertNull($user->fresh()->bio);
        $this->assertNull($user->fresh()->sharedBio());
        $this->assertNull($user->fresh()->sharedContact());
    }

    public function test_email_role_and_status_cannot_be_changed_through_the_profile(): void
    {
        $user = User::factory()->create(['email' => 'eleve@gmail.com']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/me', [
            'email' => 'autre@univ-lyon1.fr',
            'role' => 'teacher',
            'is_academic' => true,
            'password' => 'nouveau-mot-de-passe',
            'id' => 999,
            'bio' => 'ok',
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertSame('eleve@gmail.com', $fresh->email);
        $this->assertSame(UserRole::Student, $fresh->role);
        $this->assertFalse($fresh->is_academic);
        $this->assertSame($user->password, $fresh->password);
        $this->assertSame($user->id, $fresh->id);
    }

    public function test_avatar_colors_default_when_not_chosen(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/me')
            ->assertJsonPath('data.avatar_bg', config('profile.avatar.default_background'))
            ->assertJsonPath('data.avatar_fg', config('profile.avatar.default_text'));
    }

    public function test_user_chooses_avatar_colors(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/me', ['avatar_bg' => '#FEE2E2', 'avatar_fg' => '#7F1D1D'])
            ->assertOk()
            ->assertJsonPath('data.avatar_bg', '#fee2e2')
            ->assertJsonPath('data.avatar_fg', '#7f1d1d');

        // Forme courte développée, une seule écriture en base.
        $this->patchJson('/api/me', ['avatar_bg' => '#ABC'])
            ->assertOk()
            ->assertJsonPath('data.avatar_bg', '#aabbcc');
        $this->assertSame('#aabbcc', $user->fresh()->avatar_bg);

        // Remise à zéro : on retrouve les valeurs par défaut.
        $this->patchJson('/api/me', ['avatar_bg' => null, 'avatar_fg' => null])
            ->assertOk()
            ->assertJsonPath('data.avatar_bg', config('profile.avatar.default_background'));
        $this->assertNull($user->fresh()->avatar_bg);
    }

    public function test_avatar_colors_must_be_hex(): void
    {
        Sanctum::actingAs($user = User::factory()->create(['avatar_bg' => '#123456']));

        foreach (['rouge', '#12345', 'rgb(1,2,3)', 'aabbcc', '#gggggg', '#1234567', 123] as $invalid) {
            $this->patchJson('/api/me', ['avatar_bg' => $invalid])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['avatar_bg']);
        }

        $this->assertSame('#123456', $user->fresh()->avatar_bg);
    }

    public function test_profile_input_is_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/me', [
            'name' => '',
            'first_name' => str_repeat('a', 101),
            'bio' => str_repeat('a', 281),
            'contact' => str_repeat('a', 256),
            'bio_shared' => 'peut-être',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'first_name', 'bio', 'contact', 'bio_shared']);
    }
}
