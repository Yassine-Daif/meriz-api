<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_create_a_document(): void
    {
        $response = $this->postJson('/api/documents', [
            'name' => 'Mon projet',
            'content' => '{"model":{"a":1}}',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Mon projet')
            ->assertJsonPath('data.content', '{"model":{"a":1}}')
            ->assertJsonStructure(['data' => ['id', 'name', 'content', 'created_at', 'updated_at']]);

        $document = Document::findOrFail($response->json('data.id'));
        $this->assertTrue($document->user->is($this->user));
    }

    public function test_content_is_stored_and_returned_byte_for_byte(): void
    {
        // {} et 1.0 seraient altérés par un décodage puis ré-encodage.
        $content = "{\"model\":{},\"list\":[],\"zoom\":1.0,\"t\":\"é\\u00e9\",\n  \"big\":12345678901234567890}";

        $id = $this->postJson('/api/documents', ['name' => 'Fidèle', 'content' => $content])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($content, DB::table('documents')->where('id', $id)->value('content'));
        $this->assertSame($content, $this->getJson("/api/documents/{$id}")->json('data.content'));
    }

    public function test_read_a_document(): void
    {
        $document = Document::factory()->for($this->user)->create();

        $this->getJson("/api/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $document->id)
            ->assertJsonPath('data.name', $document->name)
            ->assertJsonPath('data.content', $document->content);
    }

    public function test_update_only_the_name(): void
    {
        $document = Document::factory()->for($this->user)->create(['content' => '{"v":1}']);

        $this->patchJson("/api/documents/{$document->id}", ['name' => 'Nouveau nom'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nouveau nom')
            ->assertJsonPath('data.content', '{"v":1}');

        $this->assertSame('{"v":1}', $document->fresh()->content);
    }

    public function test_update_only_the_content(): void
    {
        $document = Document::factory()->for($this->user)->create(['name' => 'Garde son nom']);

        $this->travel(1)->minute();

        $this->patchJson("/api/documents/{$document->id}", ['content' => '{"v":2}'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Garde son nom')
            ->assertJsonPath('data.content', '{"v":2}');

        $fresh = $document->fresh();
        $this->assertSame('{"v":2}', $fresh->content);
        $this->assertTrue($fresh->updated_at->greaterThan($document->updated_at));
    }

    public function test_full_update_with_put(): void
    {
        $document = Document::factory()->for($this->user)->create();

        $this->putJson("/api/documents/{$document->id}", ['name' => 'Tout neuf', 'content' => '[1,2]'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Tout neuf')
            ->assertJsonPath('data.content', '[1,2]');
    }

    public function test_delete_a_document(): void
    {
        $document = Document::factory()->for($this->user)->create();

        $this->deleteJson("/api/documents/{$document->id}")->assertNoContent();

        $this->assertModelMissing($document);
        $this->getJson("/api/documents/{$document->id}")->assertNotFound();
    }

    public function test_documents_are_deleted_with_their_owner(): void
    {
        Document::factory()->count(2)->for($this->user)->create();

        $this->user->delete();

        $this->assertDatabaseCount('documents', 0);
    }
}
