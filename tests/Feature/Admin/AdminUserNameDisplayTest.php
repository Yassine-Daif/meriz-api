<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUserNameDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_bounds_long_names_and_preserves_the_full_name(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = User::factory()->create(['first_name' => str_repeat('A', 100), 'name' => str_repeat('B', 100)]);
        Livewire::test(ManageUsers::class)
            ->assertSee(Str::limit($user->displayName(), 40))
            ->assertSee($user->displayName());
        $this->assertSame(100, mb_strlen($user->fresh()->name));
    }
}
