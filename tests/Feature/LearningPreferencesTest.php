<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LearningPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_account_keeps_an_explicit_first_choice(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('data.interface_mode', null)
            ->assertJsonPath('data.usage_category', null);
    }

    public function test_preference_survives_reload_without_changing_permissions(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->patchJson('/api/me', ['interface_mode' => 'beginner', 'usage_category' => 'teacher'])
            ->assertOk()->assertJsonPath('data.interface_mode', 'beginner')
            ->assertJsonPath('data.usage_category', 'teacher')->assertJsonPath('data.role', 'student');
        $this->getJson('/api/me')->assertJsonPath('data.interface_mode', 'beginner');
        $this->assertSame(UserRole::Student, $user->fresh()->role);
    }

    public function test_invalid_preferences_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->patchJson('/api/me', ['interface_mode' => 'admin', 'usage_category' => 'admin'])
            ->assertUnprocessable()->assertJsonValidationErrors(['interface_mode', 'usage_category']);
        $this->patchJson('/api/me', ['interface_mode' => null])->assertUnprocessable();
    }

    public function test_registration_category_does_not_grant_teacher_role_or_choose_a_mode(): void
    {
        Notification::fake();
        $this->postJson('/api/auth/register', [
            'name' => 'D’Arcy', 'first_name' => 'Anne-Marie', 'email' => 'anne@univ-reims.fr',
            'password' => 'motdepasse-solide', 'usage_category' => 'teacher',
            'role' => 'teacher', 'interface_mode' => 'complete',
        ])->assertCreated()->assertJsonPath('data.user.usage_category', 'teacher')
            ->assertJsonPath('data.user.role', 'student')->assertJsonPath('data.user.interface_mode', null);
    }
}
