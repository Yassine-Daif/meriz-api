<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Preuves du cloisonnement : un utilisateur n'atteint jamais les documents
 * d'un autre, et ne peut même pas savoir qu'ils existent.
 */
class DocumentIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Document $bobDocument;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
        $this->bobDocument = Document::factory()->for($this->bob)->create([
            'name' => 'Secret de Bob',
            'content' => '{"secret":true}',
        ]);
    }

    public function test_list_only_contains_own_documents(): void
    {
        $aliceIds = Document::factory()->count(3)->for($this->alice)->create()->pluck('id')->sort()->values();
        Document::factory()->for($this->bob)->create();

        Sanctum::actingAs($this->alice);

        $response = $this->getJson('/api/documents')->assertOk();

        $this->assertEquals($aliceIds->all(), collect($response->json('data'))->pluck('id')->sort()->values()->all());
        $this->assertSame(3, $response->json('meta.total'));
        $response->assertDontSee($this->bobDocument->id)
            ->assertDontSee('Secret de Bob');
    }

    public function test_cannot_read_another_users_document(): void
    {
        Sanctum::actingAs($this->alice);

        $this->getJson("/api/documents/{$this->bobDocument->id}")
            ->assertNotFound()
            ->assertDontSee('secret');
    }

    public function test_cannot_update_another_users_document(): void
    {
        Sanctum::actingAs($this->alice);
        $before = $this->bobDocument->fresh()->getAttributes();

        $this->travel(1)->minute();

        $this->patchJson("/api/documents/{$this->bobDocument->id}", [
            'name' => 'Piraté',
            'content' => '{"pirate":true}',
        ])->assertNotFound();

        $this->putJson("/api/documents/{$this->bobDocument->id}", [
            'name' => 'Piraté',
            'content' => '{"pirate":true}',
        ])->assertNotFound();

        $this->assertSame($before, $this->bobDocument->fresh()->getAttributes());
    }

    public function test_cannot_delete_another_users_document(): void
    {
        Sanctum::actingAs($this->alice);

        $this->deleteJson("/api/documents/{$this->bobDocument->id}")->assertNotFound();

        $this->assertModelExists($this->bobDocument);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function debugModes(): array
    {
        return ['debug désactivé' => ['0'], 'debug activé' => ['1']];
    }

    #[DataProvider('debugModes')]
    public function test_foreign_document_is_indistinguishable_from_a_missing_one(string $debug): void
    {
        config(['app.debug' => (bool) $debug]);
        Sanctum::actingAs($this->alice);

        $targets = [
            'document de Bob' => $this->bobDocument->id,
            'ULID inexistant' => (string) Str::ulid(),
            'id mal formé' => 'pas-un-id',
            'id numérique' => '1',
        ];

        foreach (['get', 'patch', 'put', 'delete'] as $method) {
            $bodies = [];

            foreach ($targets as $label => $id) {
                $response = $this->json($method, "/api/documents/{$id}", ['name' => 'x', 'content' => '{}']);

                $this->assertSame(404, $response->status(), "{$method} {$label}");
                $bodies[$label] = $response->getContent();
            }

            $this->assertCount(1, array_unique($bodies), "Réponses différentes pour {$method} : ".json_encode($bodies));
            $this->assertSame('{"message":"Ressource introuvable."}', reset($bodies));
        }
    }

    public function test_owner_cannot_be_forced_on_creation(): void
    {
        Sanctum::actingAs($this->alice);

        $response = $this->postJson('/api/documents', [
            'name' => 'Mon doc',
            'content' => '{}',
            'user_id' => $this->bob->id,
            'user' => ['id' => $this->bob->id],
        ])->assertCreated()
            ->assertJsonMissingPath('data.user_id');

        $document = Document::findOrFail($response->json('data.id'));
        $this->assertSame($this->alice->id, $document->user_id);
        $this->assertSame(1, $this->bob->documents()->count());
    }

    public function test_owner_cannot_be_changed_on_update(): void
    {
        $document = Document::factory()->for($this->alice)->create();
        Sanctum::actingAs($this->alice);

        $this->patchJson("/api/documents/{$document->id}", [
            'name' => 'Renommé',
            'user_id' => $this->bob->id,
        ])->assertOk();

        $this->assertSame($this->alice->id, $document->fresh()->user_id);
        $this->assertSame('Renommé', $document->fresh()->name);
    }

    public function test_client_cannot_choose_the_document_id(): void
    {
        Sanctum::actingAs($this->alice);

        $response = $this->postJson('/api/documents', [
            'id' => $this->bobDocument->id,
            'name' => 'Collision',
            'content' => '{}',
        ])->assertCreated();

        $this->assertNotSame($this->bobDocument->id, $response->json('data.id'));
        $this->assertSame('Secret de Bob', $this->bobDocument->fresh()->name);
    }

    public function test_every_route_requires_a_token(): void
    {
        $id = $this->bobDocument->id;

        $this->getJson('/api/documents')->assertUnauthorized();
        $this->postJson('/api/documents', ['name' => 'x', 'content' => '{}'])->assertUnauthorized();
        $this->getJson("/api/documents/{$id}")->assertUnauthorized();
        $this->patchJson("/api/documents/{$id}", ['name' => 'x'])->assertUnauthorized();
        $this->putJson("/api/documents/{$id}", ['name' => 'x'])->assertUnauthorized();
        $this->deleteJson("/api/documents/{$id}")->assertUnauthorized();

        $this->assertDatabaseCount('documents', 1);
        $this->assertSame('Secret de Bob', $this->bobDocument->fresh()->name);
    }

    public function test_revoked_token_is_refused(): void
    {
        $token = $this->bob->createToken('web');
        $token->accessToken->delete();

        $this->withToken($token->plainTextToken)
            ->getJson("/api/documents/{$this->bobDocument->id}")
            ->assertUnauthorized();
    }

    public function test_real_tokens_keep_users_apart(): void
    {
        $aliceToken = $this->alice->createToken('web')->plainTextToken;
        $bobToken = $this->bob->createToken('web')->plainTextToken;

        $this->withToken($bobToken)->getJson("/api/documents/{$this->bobDocument->id}")->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($aliceToken)->getJson("/api/documents/{$this->bobDocument->id}")->assertNotFound();
    }
}
