<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUserEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_open_and_save_each_role(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = User::factory()->create();

        foreach ([UserRole::Teacher, UserRole::Student] as $role) {
            Livewire::test(ManageUsers::class)
                ->callAction(TestAction::make('edit')->table($user), data: [
                    'name' => 'Martin',
                    'role' => $role->value,
                    'is_academic' => true,
                    'email' => 'changed@example.com',
                    'is_admin' => true,
                ])
                ->assertHasNoActionErrors();

            $originalEmail = $user->email;
            $user->refresh();
            $this->assertSame($role, $user->role);
            $this->assertSame('Martin', $user->name);
            $this->assertTrue($user->is_academic);
            $this->assertFalse($user->is_admin);
            $this->assertSame($originalEmail, $user->email);
        }
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = User::factory()->create();

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($user), data: ['role' => 'administrator'])
            ->assertHasActionErrors(['role']);

        $this->assertSame(UserRole::Student, $user->refresh()->role);
    }
}
