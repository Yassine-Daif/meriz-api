<?php

namespace Tests\Feature\Groups;

use App\Models\Document;
use App\Models\Group;
use App\Models\User;
use App\Policies\GroupPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Preuves du cloisonnement des groupes : un non-membre ne voit rien, et seul
 * le créateur administre.
 */
class GroupIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const SHARED_MARKER = 'TRAVAIL-GROUPE-7c2f';

    private User $creator;

    private User $member;

    private User $outsider;

    private Group $group;

    private Document $shared;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::factory()->create(['email' => 'chef.secret@gmail.com']);
        $this->member = User::factory()->create(['email' => 'membre.secret@gmail.com']);
        $this->outsider = User::factory()->create();

        $this->group = Group::factory()->for($this->creator, 'creator')->create(['name' => 'Projet MCD']);
        $this->group->members()->attach($this->member->id);

        $this->shared = Document::factory()->for($this->member)->forGroup($this->group)
            ->create(['content' => '{"travail":"'.self::SHARED_MARKER.'"}']);
    }

    public function test_an_outsider_sees_neither_the_group_nor_its_documents(): void
    {
        Sanctum::actingAs($this->outsider);

        $responses = [
            'liste' => $this->getJson('/api/groups'),
            'vue' => $this->getJson("/api/groups/{$this->group->id}"),
            'documents' => $this->getJson("/api/groups/{$this->group->id}/documents"),
            'document partagé' => $this->getJson("/api/documents/{$this->shared->id}"),
            'mes documents' => $this->getJson('/api/documents'),
        ];

        $responses['liste']->assertOk()->assertJsonPath('data', []);
        $responses['vue']->assertNotFound();
        $responses['documents']->assertNotFound();
        $responses['document partagé']->assertNotFound();
        $responses['mes documents']->assertOk()->assertJsonPath('data', []);

        foreach ($responses as $label => $response) {
            $this->assertStringNotContainsString(self::SHARED_MARKER, $response->getContent(), $label);
            $this->assertStringNotContainsString('Projet MCD', $response->getContent(), $label);
        }
    }

    public function test_an_outsider_cannot_write_in_the_group(): void
    {
        Sanctum::actingAs($this->outsider);
        $id = $this->group->id;

        $this->patchJson("/api/groups/{$id}", ['name' => 'Volé'])->assertNotFound();
        $this->deleteJson("/api/groups/{$id}")->assertNotFound();
        $this->postJson("/api/groups/{$id}/code")->assertNotFound();
        $this->postJson("/api/groups/{$id}/documents", ['name' => 'x', 'content' => '{}'])->assertNotFound();
        $this->deleteJson("/api/groups/{$id}/members/{$this->member->id}")->assertNotFound();
        $this->deleteJson("/api/groups/{$id}/membership")->assertNotFound();
        $this->patchJson("/api/documents/{$this->shared->id}", ['content' => '{"pirate":1}'])->assertNotFound();
        $this->deleteJson("/api/documents/{$this->shared->id}")->assertNotFound();

        $this->assertSame('Projet MCD', $this->group->fresh()->name);
        $this->assertSame('{"travail":"'.self::SHARED_MARKER.'"}', $this->shared->fresh()->content);
        $this->assertSame(2, $this->group->members()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function debugModes(): array
    {
        return ['debug désactivé' => ['0'], 'debug activé' => ['1']];
    }

    #[DataProvider('debugModes')]
    public function test_a_foreign_group_looks_like_a_missing_one(string $debug): void
    {
        config(['app.debug' => (bool) $debug]);
        Sanctum::actingAs($this->outsider);

        $targets = [
            'groupe réel' => $this->group->id,
            'ULID inexistant' => (string) Str::ulid(),
            'id mal formé' => 'pas-un-id',
        ];

        foreach ([
            ['GET', '/api/groups/%s'],
            ['PATCH', '/api/groups/%s'],
            ['DELETE', '/api/groups/%s'],
            ['POST', '/api/groups/%s/code'],
            ['GET', '/api/groups/%s/documents'],
        ] as [$method, $pattern]) {
            $bodies = [];

            foreach ($targets as $label => $id) {
                $response = $this->json($method, sprintf($pattern, $id), ['name' => 'x']);
                $this->assertSame(404, $response->status(), "{$method} {$pattern} {$label}");
                $bodies[$label] = $response->getContent();
            }

            $this->assertCount(1, array_unique($bodies), "{$method} {$pattern} : ".json_encode($bodies));
            $this->assertSame('{"message":"Ressource introuvable."}', reset($bodies));
        }
    }

    public function test_a_member_cannot_administer_even_with_invalid_data(): void
    {
        Sanctum::actingAs($this->member);
        $id = $this->group->id;
        $code = $this->group->join_code;

        foreach ([
            ['PATCH', "/api/groups/{$id}", ['name' => 'Renommé']],
            ['PATCH', "/api/groups/{$id}", []],
            ['POST', "/api/groups/{$id}/code", []],
            ['DELETE', "/api/groups/{$id}/members/{$this->creator->id}", []],
            ['DELETE', "/api/groups/{$id}", []],
        ] as [$method, $uri, $data]) {
            $this->json($method, $uri, $data)
                ->assertForbidden()
                ->assertJsonPath('message', GroupPolicy::CREATOR_ONLY_MESSAGE);
        }

        $fresh = $this->group->fresh();
        $this->assertSame('Projet MCD', $fresh->name);
        $this->assertSame($code, $fresh->join_code);
        $this->assertSame(2, $fresh->members()->count());
    }

    public function test_only_the_creator_sees_the_code(): void
    {
        Sanctum::actingAs($this->member);

        $this->getJson("/api/groups/{$this->group->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.join_code')
            ->assertJsonPath('data.my_role', 'member')
            ->assertDontSee($this->group->join_code);

        $this->getJson('/api/groups')->assertDontSee($this->group->join_code);

        Sanctum::actingAs($this->creator);
        $this->getJson("/api/groups/{$this->group->id}")
            ->assertJsonPath('data.join_code', $this->group->join_code)
            ->assertJsonPath('data.my_role', 'admin');
    }

    public function test_no_login_email_leaks_through_the_group(): void
    {
        Sanctum::actingAs($this->member);

        $body = $this->getJson("/api/groups/{$this->group->id}")->assertOk()->getContent();

        $this->assertStringNotContainsString('chef.secret@gmail.com', $body);
        $this->assertStringNotContainsString('"email"', $body);
    }

    public function test_the_client_cannot_force_creator_code_or_group(): void
    {
        Sanctum::actingAs($this->member);

        $id = $this->postJson('/api/groups', [
            'name' => 'Mon groupe',
            'creator_id' => $this->outsider->id,
            'join_code' => 'AAAAAAAA',
            'id' => $this->group->id,
        ])->assertCreated()->json('data.id');

        $created = Group::findOrFail($id);
        $this->assertSame($this->member->id, $created->creator_id);
        $this->assertNotSame('AAAAAAAA', $created->join_code);
        $this->assertNotSame($this->group->id, $created->id);

        // Un document personnel ne devient pas partagé par un group_id envoyé.
        $documentId = $this->postJson('/api/documents', [
            'name' => 'Perso',
            'content' => '{}',
            'group_id' => $this->group->id,
        ])->assertCreated()->json('data.id');

        $this->assertNull(Document::findOrFail($documentId)->group_id);
    }

    public function test_the_creator_can_only_remove_members_of_their_own_group(): void
    {
        $otherGroup = Group::factory()->create();
        $otherGroup->members()->attach($this->outsider->id);

        Sanctum::actingAs($this->creator);
        $id = $this->group->id;

        $this->deleteJson("/api/groups/{$id}/members/{$this->outsider->id}")->assertNotFound();
        $this->deleteJson("/api/groups/{$id}/members/999999")->assertNotFound();
        $this->deleteJson("/api/groups/{$id}/members/abc")->assertNotFound();
        $this->deleteJson("/api/groups/{$otherGroup->id}/members/{$this->outsider->id}")->assertNotFound();

        $this->assertTrue($otherGroup->hasMember($this->outsider));
    }

    public function test_every_route_requires_a_token(): void
    {
        $id = $this->group->id;

        foreach ([
            ['GET', '/api/groups'],
            ['POST', '/api/groups'],
            ['POST', '/api/groups/join'],
            ['GET', "/api/groups/{$id}"],
            ['PATCH', "/api/groups/{$id}"],
            ['DELETE', "/api/groups/{$id}"],
            ['POST', "/api/groups/{$id}/code"],
            ['GET', "/api/groups/{$id}/documents"],
            ['POST', "/api/groups/{$id}/documents"],
            ['DELETE', "/api/groups/{$id}/members/{$this->member->id}"],
            ['DELETE', "/api/groups/{$id}/membership"],
        ] as [$method, $uri]) {
            $this->json($method, $uri, ['name' => 'x', 'content' => '{}', 'code' => 'x'])->assertUnauthorized();
        }

        $this->assertSame('Projet MCD', $this->group->fresh()->name);
        $this->assertSame(2, $this->group->members()->count());
    }
}
