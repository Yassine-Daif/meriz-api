<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\CreateGroupDocument;
use App\Models\Document;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private User $alice;

    private User $bob;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::factory()->create();
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();

        $this->group = Group::factory()->for($this->creator, 'creator')->create();
        $this->group->members()->attach([$this->alice->id, $this->bob->id]);
    }

    public function test_a_member_creates_a_shared_document(): void
    {
        Sanctum::actingAs($this->alice);

        $response = $this->postJson("/api/groups/{$this->group->id}/documents", [
            'name' => 'MCD commun',
            'content' => '{"model":{}}',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'MCD commun')
            ->assertJsonPath('data.group_id', $this->group->id)
            ->assertJsonPath('data.content', '{"model":{}}');

        $document = Document::findOrFail($response->json('data.id'));
        $this->assertSame($this->alice->id, $document->user_id);
        $this->assertSame($this->group->id, $document->group_id);
    }

    public function test_all_members_read_and_edit_the_shared_document(): void
    {
        $content = '{"model":{},"zoom":1.0,"t":"é\\u00e9"}';

        Sanctum::actingAs($this->alice);
        $id = $this->postJson("/api/groups/{$this->group->id}/documents", [
            'name' => 'Commun', 'content' => '{"v":1}',
        ])->assertCreated()->json('data.id');

        // Bob, simple membre, lit et modifie.
        Sanctum::actingAs($this->bob);
        $this->getJson("/api/documents/{$id}")->assertOk()->assertJsonPath('data.content', '{"v":1}');
        $this->patchJson("/api/documents/{$id}", ['content' => $content])->assertOk();

        // Le contenu est gardé octet pour octet.
        $this->assertSame($content, DB::table('documents')->where('id', $id)->value('content'));

        // Le créateur du groupe aussi.
        Sanctum::actingAs($this->creator);
        $this->getJson("/api/documents/{$id}")->assertOk()->assertJsonPath('data.content', $content);
    }

    public function test_group_documents_and_personal_documents_stay_separate(): void
    {
        Sanctum::actingAs($this->alice);
        $shared = $this->postJson("/api/groups/{$this->group->id}/documents", [
            'name' => 'Commun', 'content' => '{}',
        ])->assertCreated()->json('data.id');
        $personal = $this->postJson('/api/documents', ['name' => 'Perso', 'content' => '{}'])
            ->assertCreated()->json('data.id');

        // La liste personnelle ne montre que le document personnel…
        $this->getJson('/api/documents')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$personal]);

        // … et la liste du groupe que le partagé.
        $this->getJson("/api/groups/{$this->group->id}/documents")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$shared]);

        // Le document personnel d'Alice reste fermé aux autres membres.
        Sanctum::actingAs($this->bob);
        $this->getJson("/api/documents/{$personal}")->assertNotFound();
    }

    public function test_only_the_author_or_the_group_creator_can_delete(): void
    {
        Sanctum::actingAs($this->alice);
        $id = $this->postJson("/api/groups/{$this->group->id}/documents", [
            'name' => 'Commun', 'content' => '{}',
        ])->assertCreated()->json('data.id');

        // Un autre membre ne supprime pas le travail d'Alice.
        Sanctum::actingAs($this->bob);
        $this->deleteJson("/api/documents/{$id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Seul l\'auteur du document ou le créateur du groupe peut le supprimer.');
        $this->assertDatabaseCount('documents', 1);

        // Le créateur du groupe, oui.
        Sanctum::actingAs($this->creator);
        $this->deleteJson("/api/documents/{$id}")->assertNoContent();
        $this->assertDatabaseCount('documents', 0);

        // L'auteur aussi, sur un autre document.
        Sanctum::actingAs($this->alice);
        $mine = $this->postJson("/api/groups/{$this->group->id}/documents", [
            'name' => 'Le mien', 'content' => '{}',
        ])->assertCreated()->json('data.id');
        $this->deleteJson("/api/documents/{$mine}")->assertNoContent();
    }

    public function test_input_is_validated(): void
    {
        config(['documents.max_content_bytes' => 50]);
        Sanctum::actingAs($this->alice);
        $url = "/api/groups/{$this->group->id}/documents";

        $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'content']);
        $this->postJson($url, ['name' => 'x', 'content' => 'pas du json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['content']);
        $this->postJson($url, ['name' => 'x', 'content' => '"'.str_repeat('a', 60).'"'])
            ->assertUnprocessable()->assertJsonValidationErrors(['content']);

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_document_count_per_group_is_bounded_without_touching_personal_quota(): void
    {
        config(['groups.max_documents_per_group' => 2, 'documents.max_per_user' => 1]);
        Document::factory()->count(2)->for($this->alice)->forGroup($this->group)->create();

        Sanctum::actingAs($this->alice);
        $this->postJson("/api/groups/{$this->group->id}/documents", ['name' => 'De trop', 'content' => '{}'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.content.0', CreateGroupDocument::QUOTA_MESSAGE);

        // Son quota personnel reste intact : les documents partagés n'y entrent pas.
        $this->postJson('/api/documents', ['name' => 'Perso', 'content' => '{}'])->assertCreated();
    }
}
