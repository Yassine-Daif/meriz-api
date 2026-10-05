<?php

namespace Tests\Feature\Groups;

use App\Models\Document;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La co-édition d'un document de groupe passe par le même canal de présence
 * que le reste. Seule la règle d'accès a été étendue.
 */
class GroupCollaborationTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private User $alice;

    private User $outsider;

    private Group $group;

    private Document $shared;

    protected function setUp(): void
    {
        parent::setUp();

        // Le diffuseur « null » des tests accepte tout : on bascule sur un
        // vrai diffuseur et on recharge le fichier de canaux.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'cle-de-test',
            'broadcasting.connections.reverb.secret' => 'secret-de-test',
            'broadcasting.connections.reverb.app_id' => 'meriz-test',
        ]);
        require base_path('routes/channels.php');

        $this->creator = User::factory()->create();
        $this->alice = User::factory()->withSharedProfile()->create(['email' => 'alice.secret@gmail.com']);
        $this->outsider = User::factory()->create();

        $this->group = Group::factory()->for($this->creator, 'creator')->create();
        $this->group->members()->attach($this->alice->id);

        $this->shared = Document::factory()->for($this->alice)->forGroup($this->group)->create();
    }

    private function join(?string $documentId = null)
    {
        return $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-documents.'.($documentId ?? $this->shared->id),
        ]);
    }

    public function test_every_member_can_collaborate_on_a_group_document(): void
    {
        // L'auteur…
        Sanctum::actingAs($this->alice);
        $response = $this->join()->assertOk();

        $presence = json_decode($response->json('channel_data'), true);
        $this->assertSame(
            ['id', 'name', 'first_name', 'role', 'avatar_bg', 'avatar_fg'],
            array_keys($presence['user_info']),
        );

        // … et un autre membre, ici le créateur du groupe.
        Sanctum::actingAs($this->creator);
        $this->join()->assertOk();
    }

    public function test_presence_still_carries_no_email_or_bio(): void
    {
        Sanctum::actingAs($this->alice);

        $body = $this->join()->assertOk()->getContent();

        $this->assertStringNotContainsString('alice.secret@gmail.com', $body);
        $this->assertStringNotContainsString($this->alice->bio, $body);
        $this->assertStringNotContainsString($this->alice->contact, $body);
    }

    public function test_an_outsider_cannot_collaborate(): void
    {
        Sanctum::actingAs($this->outsider);

        $this->join()->assertForbidden();
    }

    public function test_leaving_the_group_closes_the_channel_even_for_the_author(): void
    {
        Sanctum::actingAs($this->alice);
        $this->join()->assertOk();
        $this->getJson("/api/documents/{$this->shared->id}")->assertOk();

        // Alice quitte le groupe. Le document reste dans l'espace commun :
        // elle n'y a plus accès, même si c'est elle qui l'a créé.
        $this->group->members()->detach($this->alice->id);

        $this->join()->assertForbidden();
        $this->getJson("/api/documents/{$this->shared->id}")->assertNotFound();
        $this->patchJson("/api/documents/{$this->shared->id}", ['content' => '{"pirate":1}'])->assertNotFound();
        $this->deleteJson("/api/documents/{$this->shared->id}")->assertNotFound();

        // Le document, lui, reste au groupe.
        $this->assertModelExists($this->shared);
        Sanctum::actingAs($this->creator);
        $this->join()->assertOk();
    }

    public function test_a_personal_document_stays_closed_to_group_mates(): void
    {
        $personal = Document::factory()->for($this->alice)->create();

        Sanctum::actingAs($this->creator);
        $this->join($personal->id)->assertForbidden();

        Sanctum::actingAs($this->alice);
        $this->join($personal->id)->assertOk();
    }

    public function test_joining_requires_a_token(): void
    {
        $this->join()->assertUnauthorized();
    }
}
