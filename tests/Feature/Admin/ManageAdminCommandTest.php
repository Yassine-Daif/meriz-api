<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le droit d'administrer ne se donne qu'en ligne de commande, donc cette
 * commande est la seule porte : elle mérite d'être tenue par des tests.
 */
class ManageAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_grants_the_right(): void
    {
        $user = User::factory()->create(['email' => 'claire@ac-lyon.fr']);

        $this->artisan('meriz:admin', ['email' => 'claire@ac-lyon.fr'])
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->isAdmin());
    }

    public function test_it_revokes_the_right(): void
    {
        $user = User::factory()->admin()->create(['email' => 'claire@ac-lyon.fr']);

        $this->artisan('meriz:admin', ['email' => 'claire@ac-lyon.fr', '--revoke' => true])
            ->assertSuccessful();

        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_the_email_is_matched_whatever_the_case(): void
    {
        $user = User::factory()->create(['email' => 'claire@ac-lyon.fr']);

        $this->artisan('meriz:admin', ['email' => '  CLAIRE@AC-LYON.FR '])
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->isAdmin());
    }

    public function test_it_refuses_an_unknown_address(): void
    {
        $this->artisan('meriz:admin', ['email' => 'personne@example.com'])
            ->assertFailed();

        $this->assertSame(0, User::where('is_admin', true)->count());
    }

    public function test_it_refuses_a_deleted_account(): void
    {
        $user = User::factory()->create(['email' => 'partie@example.com']);
        $user->forceFill(['anonymized_at' => now()])->save();

        $this->artisan('meriz:admin', ['email' => 'partie@example.com'])
            ->assertFailed();

        $this->assertFalse($user->fresh()->isAdmin());
    }
}
