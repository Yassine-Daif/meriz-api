<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_account_can_become_teacher(): void
    {
        $user = User::factory()->academic()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/me/teacher-role')
            ->assertOk()
            ->assertJsonPath('data.role', 'teacher');

        $this->assertSame(UserRole::Teacher, $user->fresh()->role);
    }

    public function test_non_academic_account_cannot_become_teacher(): void
    {
        $user = User::factory()->create(['email' => 'bob@gmail.com']);
        Sanctum::actingAs($user);

        $this->postJson('/api/me/teacher-role')
            ->assertForbidden()
            ->assertJsonPath('message', UserPolicy::NOT_ACADEMIC_MESSAGE);

        $this->assertSame(UserRole::Student, $user->fresh()->role);
    }

    public function test_academic_status_comes_from_the_server_not_the_email_alone(): void
    {
        // Le flag is_academic fait foi, pas une relecture de l'email côté requête.
        $user = User::factory()->create(['email' => 'bob@univ-lyon1.fr', 'is_academic' => false]);
        Sanctum::actingAs($user);

        $this->postJson('/api/me/teacher-role')->assertForbidden();
    }

    public function test_activation_requires_a_token(): void
    {
        $this->postJson('/api/me/teacher-role')->assertUnauthorized();
    }

    public function test_activation_is_idempotent_for_a_teacher(): void
    {
        $user = User::factory()->teacher()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/me/teacher-role')
            ->assertOk()
            ->assertJsonPath('data.role', 'teacher');
    }

    public function test_full_flow_from_registration_to_teacher(): void
    {
        $token = $this->postJson('/api/auth/register', [
            'name' => 'Claire Prof',
            'email' => 'claire@ac-lyon.fr',
            'password' => 'motdepasse-solide',
        ])->json('data.token');

        $this->withToken($token)->postJson('/api/me/teacher-role')
            ->assertOk()
            ->assertJsonPath('data.role', 'teacher')
            ->assertJsonPath('data.is_academic', true);
    }
}
