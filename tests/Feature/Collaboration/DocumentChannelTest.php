<?php

namespace Tests\Feature\Collaboration;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Qui peut rejoindre la session de co-édition d'un document.
 *
 * On appelle la vraie route d'autorisation des canaux, avec un jeton : c'est
 * le seul chemin d'entrée, et il passe par DocumentPolicy::collaborate.
 */
class DocumentChannelTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $alice;

    private User $bob;

    private User $outsider;

    private Classroom $classroom;

    private Assignment $assignment;

    private Document $work;

    protected function setUp(): void
    {
        parent::setUp();

        // Les tests tournent sur le diffuseur « null », qui accepte tout sans
        // consulter les canaux. On bascule donc sur un vrai diffuseur, avec
        // des identifiants factices, et on recharge le vrai fichier de canaux.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'cle-de-test',
            'broadcasting.connections.reverb.secret' => 'secret-de-test',
            'broadcasting.connections.reverb.app_id' => 'meriz-test',
        ]);
        require base_path('routes/channels.php');

        $this->teacher = User::factory()->teacher()->create(['email' => 'prof.secret@univ-lyon1.fr']);
        $this->alice = User::factory()->withSharedProfile()->create([
            'email' => 'alice.secret@gmail.com',
            'name' => 'Martin',
            'first_name' => 'Alice',
        ]);
        $this->bob = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $this->classroom->members()->attach([$this->alice->id, $this->bob->id]);

        $this->assignment = Assignment::factory()->for($this->classroom)->published()->withBase()
            ->withSolution('{"corrige":"CORRIGE-COLLAB-5f1b"}')
            ->create();

        $this->work = Document::factory()->for($this->alice)->forAssignment($this->assignment)->create();
    }

    private function join(?string $documentId = null)
    {
        return $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-documents.'.($documentId ?? $this->work->id),
        ]);
    }

    private function enableTracking(): void
    {
        $this->assignment->forceFill(['live_tracking' => true, 'live_tracking_enabled_at' => now()])->save();
    }

    public function test_the_owner_joins_and_carries_only_display_identity(): void
    {
        Sanctum::actingAs($this->alice);

        $response = $this->join()->assertOk();

        $presence = json_decode($response->json('channel_data'), true);

        $this->assertSame((string) $this->alice->id, (string) $presence['user_id']);
        $this->assertSame(
            ['id', 'name', 'first_name', 'role', 'avatar_bg', 'avatar_fg'],
            array_keys($presence['user_info']),
        );
        $this->assertSame('Alice', $presence['user_info']['first_name']);
        $this->assertSame('student', $presence['user_info']['role']);

        // Ni email, ni présentation, ni contact, ni corrigé dans le canal.
        $body = $response->getContent();
        $this->assertStringNotContainsString('alice.secret@gmail.com', $body);
        $this->assertStringNotContainsString('CORRIGE-COLLAB-5f1b', $body);
        $this->assertStringNotContainsString($this->alice->bio, $body);
        $this->assertStringNotContainsString($this->alice->contact, $body);
    }

    public function test_the_teacher_joins_when_tracking_is_on(): void
    {
        $this->enableTracking();
        Sanctum::actingAs($this->teacher);

        $response = $this->join()->assertOk();
        $presence = json_decode($response->json('channel_data'), true);

        $this->assertSame((string) $this->teacher->id, (string) $presence['user_id']);
        $this->assertSame('teacher', $presence['user_info']['role']);
        $this->assertStringNotContainsString('prof.secret@univ-lyon1.fr', $response->getContent());
    }

    public function test_the_teacher_is_refused_while_tracking_is_off(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->join()->assertForbidden();

        $this->assertNull($this->work->fresh()->last_observed_at);
    }

    public function test_classmates_other_teachers_and_outsiders_are_refused(): void
    {
        $this->enableTracking();

        foreach ([$this->bob, $this->outsider, User::factory()->teacher()->create()] as $intruder) {
            Sanctum::actingAs($intruder);
            $this->join()->assertForbidden();
        }

        $this->assertNull($this->work->fresh()->last_observed_at);
    }

    public function test_a_personal_document_is_never_reachable_by_a_teacher(): void
    {
        $this->enableTracking();
        $personal = Document::factory()->for($this->alice)->create(['name' => 'Journal']);

        Sanctum::actingAs($this->teacher);
        $this->join($personal->id)->assertForbidden();

        // Son propriétaire, lui, peut toujours y co-éditer.
        Sanctum::actingAs($this->alice);
        $this->join($personal->id)->assertOk();
    }

    public function test_a_teacher_cannot_reach_work_of_another_assignment(): void
    {
        $this->enableTracking();

        // Travail d'Alice sur un devoir d'un autre prof.
        $otherClassroom = Classroom::factory()->create();
        $otherClassroom->members()->attach($this->alice->id);
        $otherAssignment = Assignment::factory()->for($otherClassroom)->published()
            ->create(['live_tracking' => true]);
        $otherWork = Document::factory()->for($this->alice)->forAssignment($otherAssignment)->create();

        Sanctum::actingAs($this->teacher);
        $this->join($otherWork->id)->assertForbidden();
    }

    public function test_on_their_own_draft_the_teacher_stays_allowed(): void
    {
        // Un devoir dépublié après coup : le prof voit ses propres brouillons,
        // comme en observation HTTP. La règle observeLive décide, et elle est
        // la même partout.
        $draft = Assignment::factory()->for($this->classroom)->withBase()
            ->create(['live_tracking' => true]);
        $draftWork = Document::factory()->for($this->alice)->forAssignment($draft)->create();

        Sanctum::actingAs($this->teacher);
        $this->join($draftWork->id)->assertOk();

        // Mais le brouillon d'un autre prof reste fermé.
        $foreignDraft = Assignment::factory()->create(['live_tracking' => true]);
        $foreignWork = Document::factory()->for($this->alice)->forAssignment($foreignDraft)->create();
        $this->join($foreignWork->id)->assertForbidden();

        // L'élève continue de travailler sur le sien.
        Sanctum::actingAs($this->alice);
        $this->join($draftWork->id)->assertOk();
    }

    public function test_unknown_documents_are_refused(): void
    {
        $this->enableTracking();
        Sanctum::actingAs($this->teacher);

        $this->join((string) Str::ulid())->assertForbidden();
        $this->join('pas-un-id')->assertForbidden();
    }

    public function test_joining_requires_a_token(): void
    {
        $this->enableTracking();

        $this->join()->assertUnauthorized();

        $this->assertNull($this->work->fresh()->last_observed_at);
    }

    public function test_a_teacher_joining_leaves_a_trace_the_student_sees(): void
    {
        $this->enableTracking();

        Sanctum::actingAs($this->teacher);
        $this->join()->assertOk();

        $observedAt = $this->work->fresh()->last_observed_at;
        $this->assertNotNull($observedAt);

        Sanctum::actingAs($this->alice);
        $this->getJson("/api/documents/{$this->work->id}")
            ->assertOk()
            ->assertJsonPath('data.last_observed_at', $observedAt->toIso8601String());
    }

    public function test_the_student_joining_their_own_work_leaves_no_trace(): void
    {
        Sanctum::actingAs($this->alice);

        $this->join()->assertOk();

        $this->assertNull($this->work->fresh()->last_observed_at);
    }
}
