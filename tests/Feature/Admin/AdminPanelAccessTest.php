<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La zone admin est une surface à part : pages rendues, session, et un seul
 * droit d'entrée. Ces tests tiennent les deux frontières — qui entre dans le
 * panneau, et l'étanchéité entre le panneau et l'API.
 */
class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_is_sent_to_the_panel_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_the_login_page_answers(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_an_ordinary_account_is_refused(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin')->assertForbidden();
    }

    public function test_a_teacher_is_refused_too(): void
    {
        // Être prof ne donne rien ici : l'administration est un autre droit.
        $this->actingAs(User::factory()->teacher()->create());

        $this->get('/admin')->assertForbidden();
    }

    public function test_an_admin_gets_in(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin')->assertOk();
    }

    public function test_a_deleted_admin_is_refused(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->forceFill(['anonymized_at' => now()])->save();

        $this->actingAs($admin);

        $this->get('/admin')->assertForbidden();
    }

    public function test_an_api_token_does_not_open_the_panel(): void
    {
        // Jeton d'API, pas de session : le panneau renvoie à sa connexion.
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_an_admin_session_does_not_open_the_api(): void
    {
        // L'inverse : sanctum n'accepte que des jetons, jamais une session.
        $this->actingAs(User::factory()->admin()->create());

        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_the_panel_pages_are_html_not_json(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->get('/admin')->assertOk();

        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));

        // La CSP de l'API bloquerait tout le style du panneau.
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    public function test_a_missing_admin_page_is_not_rendered_as_json(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->get('/admin/page-qui-nexiste-pas')->assertNotFound();

        $this->assertStringNotContainsString('Ressource introuvable.', $response->getContent());
    }

    public function test_the_api_still_answers_json_everywhere(): void
    {
        // Le panneau ne change rien au comportement de l'API.
        $this->getJson('/api/me')->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');

        $this->getJson('/api/health')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_the_admin_flag_cannot_be_set_through_the_api(): void
    {
        $token = $this->postJson('/api/auth/register', [
            'name' => 'Martin',
            'first_name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'motdepasse-solide',
            'is_admin' => true,
        ])->assertCreated()->json('data.token');

        $alice = User::firstWhere('email', 'alice@example.com');
        $this->assertFalse($alice->isAdmin());

        $this->withToken($token)->patchJson('/api/me', [
            'name' => 'Martin',
            'is_admin' => true,
        ])->assertOk();

        $this->assertFalse($alice->fresh()->isAdmin());
    }

    public function test_the_admin_flag_is_not_mass_assignable(): void
    {
        // Deuxième barrière, prise seule : même si un jour un champ passait
        // la validation, le modèle refuse d'écrire is_admin en masse.
        $user = User::factory()->create();

        $user->update(['name' => 'Martin', 'is_admin' => true]);
        $user->fill(['is_admin' => true])->save();

        $this->assertFalse($user->fresh()->isAdmin());
        $this->assertSame('Martin', $user->fresh()->name);
    }

    public function test_the_admin_flag_never_appears_in_an_api_response(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/me')->assertOk();

        $response->assertJsonMissingPath('data.is_admin');
        $this->assertStringNotContainsString('is_admin', $response->getContent());
    }
}
