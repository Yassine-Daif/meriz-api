<?php

namespace Tests\Feature\Localisation;

use App\Exceptions\ApiErrorMessages;
use App\Models\Document;
use App\Models\User;
use App\Policies\ClassroomPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Plus un mot d'anglais dans une réponse. Le balayage cherche des marqueurs
 * dans le corps entier, pas seulement dans le champ message.
 */
class FrenchMessagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Marqueurs anglais des messages par défaut de Laravel et de Symfony.
     *
     * @var array<int, string>
     */
    private const ENGLISH_MARKERS = [
        'The ',
        ' field ',
        'must be',
        'has already been taken',
        'Unauthenticated',
        'Too Many',
        'This action is unauthorized',
        'not supported for route',
        'Server Error',
        'more error',
        'given data was invalid',
        'Previous',
        'Next',
        'laquo',
        'raquo',
    ];

    private function assertNoEnglish(TestResponse $response): void
    {
        $body = $response->getContent();

        foreach (self::ENGLISH_MARKERS as $marker) {
            $this->assertStringNotContainsString(
                $marker,
                $body,
                "Message anglais dans la réponse : « {$marker} »",
            );
        }
    }

    public function test_the_application_speaks_french(): void
    {
        $this->assertSame('fr', config('app.locale'));
        $this->assertSame('fr', config('app.fallback_locale'));
    }

    public function test_french_is_the_default_even_without_an_environment_variable(): void
    {
        // Sans APP_LOCALE dans l'environnement, par exemple sur un dépôt
        // fraîchement cloné ou un serveur mal réglé, le défaut doit rester
        // le français, pas l'anglais.
        $previous = [
            $_ENV['APP_LOCALE'] ?? null,
            $_ENV['APP_FALLBACK_LOCALE'] ?? null,
        ];

        try {
            unset(
                $_ENV['APP_LOCALE'], $_SERVER['APP_LOCALE'],
                $_ENV['APP_FALLBACK_LOCALE'], $_SERVER['APP_FALLBACK_LOCALE'],
            );

            // Laravel lit aussi l'environnement du processus.
            putenv('APP_LOCALE');
            putenv('APP_FALLBACK_LOCALE');

            $config = require config_path('app.php');

            $this->assertSame('fr', $config['locale']);
            $this->assertSame('fr', $config['fallback_locale']);
        } finally {
            [$locale, $fallback] = $previous;

            foreach (['APP_LOCALE' => $locale, 'APP_FALLBACK_LOCALE' => $fallback] as $key => $value) {
                unset($_ENV[$key], $_SERVER[$key]);

                if ($value !== null) {
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                    putenv("{$key}={$value}");
                }
            }
        }
    }

    public function test_validation_messages_are_in_french(): void
    {
        $response = $this->postJson('/api/auth/register', [])->assertStatus(422);

        $response->assertJsonPath('errors.email.0', 'Le champ adresse email est obligatoire.')
            ->assertJsonPath('errors.password.0', 'Le champ mot de passe est obligatoire.')
            ->assertJsonPath('errors.first_name.0', 'Le champ prénom est obligatoire.')
            ->assertJsonPath('errors.name.0', 'Le champ nom est obligatoire.');

        $this->assertNoEnglish($response);
    }

    public function test_the_error_summary_is_in_french(): void
    {
        // Le résumé en tête de réponse passe par le traducteur avec la
        // phrase anglaise comme clé : il lui faut lang/fr.json.
        $this->postJson('/api/auth/register', [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Le champ nom est obligatoire. (et 3 autres erreurs)');

        // Singulier.
        $this->postJson('/api/auth/register', ['name' => 'Martin', 'first_name' => 'Alice'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Le champ adresse email est obligatoire. (et 1 autre erreur)');
    }

    public function test_a_taken_email_answers_in_french(): void
    {
        User::factory()->create(['email' => 'alice@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Martin',
            'first_name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'motdepasse-solide',
        ])->assertStatus(422);

        $response->assertJsonPath('errors.email.0', 'La valeur du champ adresse email est déjà utilisée.');
        $this->assertNoEnglish($response);
    }

    public function test_type_and_size_messages_are_in_french(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/documents', [
            'name' => str_repeat('x', 300),
            'content' => 'pas du json',
        ])->assertStatus(422);

        $response->assertJsonPath('errors.name.0', 'Le champ nom ne peut pas contenir plus de 255 caractères.')
            ->assertJsonPath('errors.content.0', 'Le champ contenu doit être du JSON valide.');

        $this->assertNoEnglish($response);
    }

    public function test_a_custom_rule_keeps_its_own_french_message(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/documents', [
            'name' => 'Trop gros',
            'content' => '{"x":"'.str_repeat('a', config('documents.max_content_bytes')).'"}',
        ])->assertStatus(422);

        $this->assertStringContainsString(
            'dépasse la taille maximale',
            (string) $response->json('errors.content.0'),
        );
        $this->assertNoEnglish($response);
    }

    public function test_an_unauthenticated_call_answers_in_french(): void
    {
        $response = $this->getJson('/api/me')->assertUnauthorized();

        $response->assertExactJson(['message' => ApiErrorMessages::UNAUTHENTICATED]);
        $this->assertNoEnglish($response);
    }

    public function test_a_missing_resource_answers_in_french(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/documents/01hzzzzzzzzzzzzzzzzzzzzzzz')->assertNotFound();

        $response->assertExactJson(['message' => ApiErrorMessages::NOT_FOUND]);
        $this->assertNoEnglish($response);
    }

    public function test_an_unknown_route_answers_in_french(): void
    {
        $response = $this->getJson('/api/route-qui-nexiste-pas')->assertNotFound();

        $response->assertExactJson(['message' => ApiErrorMessages::NOT_FOUND]);
        $this->assertNoEnglish($response);
    }

    public function test_a_wrong_method_answers_in_french_without_naming_the_route(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->json('PUT', '/api/me')->assertStatus(405);

        $response->assertExactJson(['message' => ApiErrorMessages::METHOD_NOT_ALLOWED]);
        $this->assertNoEnglish($response);

        // La réponse ne décrit pas la table de routage.
        $this->assertStringNotContainsString('api/me', $response->getContent());
        $this->assertStringNotContainsString('PATCH', $response->getContent());
    }

    public function test_a_rate_limited_call_answers_in_french_and_keeps_its_headers(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/health')->assertOk();
        }

        $response = $this->getJson('/api/health')->assertStatus(429);

        $response->assertExactJson(['message' => ApiErrorMessages::TOO_MANY_REQUESTS]);
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertNoEnglish($response);
    }

    public function test_a_policy_message_is_not_replaced_by_the_french_default(): void
    {
        // Un élève qui tente de créer une classe : le refus vient de la
        // Policy, en français, et doit sortir intact.
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/classrooms', ['name' => 'Ma classe'])
            ->assertForbidden();

        $response->assertJsonPath('message', ClassroomPolicy::TEACHER_ONLY_MESSAGE);
        $this->assertNoEnglish($response);
    }

    public function test_a_paginated_list_has_french_labels(): void
    {
        $user = User::factory()->create();
        Document::factory()->for($user)->count(3)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/documents?per_page=1')->assertOk();

        $this->assertNoEnglish($response);

        $labels = collect($response->json('meta.links'))->pluck('label')->implode(' ');
        $this->assertStringContainsString('Précédent', $labels);
        $this->assertStringContainsString('Suivant', $labels);
    }
}
