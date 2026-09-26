<?php

namespace Tests\Feature\Live;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Document;
use App\Models\User;
use App\Policies\AssignmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Preuves du cloisonnement du suivi en direct. On lit ici le travail en
 * cours de mineurs : rien ne doit sortir sans drapeau levé, et jamais un
 * document personnel.
 */
class LiveTrackingIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const WORK_MARKER = 'TRAVAIL-DEVOIR-4f1a';

    private const PRIVATE_MARKER = 'JOURNAL-INTIME-9c3d';

    private const OTHER_WORK_MARKER = 'AUTRE-DEVOIR-2b7e';

    private User $teacher;

    private User $alice;

    private User $bob;

    private User $outsider;

    private Classroom $classroom;

    private Assignment $assignment;

    private Document $work;

    private Document $privateDocument;

    private Document $otherWork;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
        $this->alice = User::factory()->create(['email' => 'alice.secret@gmail.com']);
        $this->bob = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $this->classroom->members()->attach([$this->alice->id, $this->bob->id]);

        $this->assignment = Assignment::factory()->for($this->classroom)->published()->withBase()->create();
        $otherAssignment = Assignment::factory()->for($this->classroom)->published()->create();

        // Le travail lié au devoir observé.
        $this->work = Document::factory()->for($this->alice)->forAssignment($this->assignment)
            ->create(['content' => '{"travail":"'.self::WORK_MARKER.'"}']);

        // Un document personnel, sans rapport avec un devoir.
        $this->privateDocument = Document::factory()->for($this->alice)
            ->create(['name' => 'Notes perso', 'content' => '{"prive":"'.self::PRIVATE_MARKER.'"}']);

        // Un travail lié à un autre devoir.
        $this->otherWork = Document::factory()->for($this->alice)->forAssignment($otherAssignment)
            ->create(['content' => '{"autre":"'.self::OTHER_WORK_MARKER.'"}']);
    }

    private function enableTracking(): void
    {
        $this->assignment->forceFill(['live_tracking' => true, 'live_tracking_enabled_at' => now()])->save();
    }

    public function test_nothing_is_readable_while_tracking_is_off(): void
    {
        Sanctum::actingAs($this->teacher);

        $list = $this->getJson("/api/assignments/{$this->assignment->id}/live");
        $snapshot = $this->getJson("/api/assignments/{$this->assignment->id}/live/{$this->alice->id}");

        foreach ([$list, $snapshot] as $response) {
            $response->assertForbidden()
                ->assertJsonPath('message', AssignmentPolicy::TRACKING_OFF_MESSAGE);
            $this->assertStringNotContainsString(self::WORK_MARKER, $response->getContent());
        }

        // Aucune trace de consultation n'a été écrite.
        $this->assertNull($this->work->fresh()->last_observed_at);
    }

    public function test_another_teacher_cannot_track_or_observe(): void
    {
        $this->enableTracking();
        Sanctum::actingAs(User::factory()->teacher()->create());
        $id = $this->assignment->id;

        $this->postJson("/api/assignments/{$id}/live-tracking")->assertNotFound();
        $this->deleteJson("/api/assignments/{$id}/live-tracking")->assertNotFound();
        $this->getJson("/api/assignments/{$id}/live")->assertNotFound();
        $this->getJson("/api/assignments/{$id}/live/{$this->alice->id}")->assertNotFound();

        $this->assertTrue($this->assignment->fresh()->liveTrackingEnabled());
        $this->assertNull($this->work->fresh()->last_observed_at);
    }

    public function test_a_student_can_never_observe(): void
    {
        $this->enableTracking();
        Sanctum::actingAs($this->bob);
        $id = $this->assignment->id;

        $this->getJson("/api/assignments/{$id}/live")
            ->assertForbidden()
            ->assertJsonPath('message', AssignmentPolicy::OWNER_ONLY_MESSAGE);

        $response = $this->getJson("/api/assignments/{$id}/live/{$this->alice->id}")->assertForbidden();
        $this->assertStringNotContainsString(self::WORK_MARKER, $response->getContent());

        // Ni observer son propre travail par ce chemin, ni toucher au drapeau.
        $this->getJson("/api/assignments/{$id}/live/{$this->bob->id}")->assertForbidden();
        $this->postJson("/api/assignments/{$id}/live-tracking")->assertForbidden();
        $this->deleteJson("/api/assignments/{$id}/live-tracking")->assertForbidden();

        $this->assertTrue($this->assignment->fresh()->liveTrackingEnabled());
        $this->assertNull($this->work->fresh()->last_observed_at);
    }

    public function test_only_the_work_of_this_assignment_is_readable(): void
    {
        $this->enableTracking();
        Sanctum::actingAs($this->teacher);

        $response = $this->getJson("/api/assignments/{$this->assignment->id}/live/{$this->alice->id}")->assertOk();
        $body = $response->getContent();

        // Le bon travail sort…
        $response->assertJsonPath('data.document_id', $this->work->id)
            ->assertJsonPath('data.content', '{"travail":"'.self::WORK_MARKER.'"}');

        // … et rien d'autre.
        $this->assertStringNotContainsString(self::PRIVATE_MARKER, $body);
        $this->assertStringNotContainsString(self::OTHER_WORK_MARKER, $body);
        $this->assertStringNotContainsString($this->privateDocument->id, $body);
        $this->assertStringNotContainsString($this->otherWork->id, $body);
        $this->assertStringNotContainsString('alice.secret@gmail.com', $body);

        // Les documents de l'élève restent hors de portée du prof.
        foreach ([$this->privateDocument, $this->otherWork, $this->work] as $document) {
            $this->getJson("/api/documents/{$document->id}")->assertNotFound();
        }
    }

    public function test_observing_someone_who_is_not_a_member_is_refused(): void
    {
        $this->enableTracking();
        Sanctum::actingAs($this->teacher);
        $id = $this->assignment->id;

        $this->getJson("/api/assignments/{$id}/live/{$this->outsider->id}")->assertNotFound();
        $this->getJson("/api/assignments/{$id}/live/999999")->assertNotFound();
        $this->getJson("/api/assignments/{$id}/live/abc")->assertNotFound();
        $this->getJson("/api/assignments/{$id}/live/{$this->teacher->id}")->assertNotFound();
    }

    public function test_a_former_member_is_no_longer_observable(): void
    {
        $this->enableTracking();
        Sanctum::actingAs($this->teacher);
        $url = "/api/assignments/{$this->assignment->id}/live/{$this->alice->id}";

        // Tant qu'elle est dans la classe, son travail est lisible…
        $this->getJson($url)->assertOk();

        // … mais plus après son départ, même si le document existe toujours.
        $this->classroom->members()->detach($this->alice->id);

        $response = $this->getJson($url)->assertNotFound();
        $this->assertStringNotContainsString(self::WORK_MARKER, $response->getContent());

        $list = $this->getJson("/api/assignments/{$this->assignment->id}/live")->assertOk();
        $this->assertSame([$this->bob->id], collect($list->json('data'))->pluck('student.id')->all());

        $this->assertModelExists($this->work);
    }

    public function test_observation_is_read_only(): void
    {
        $this->enableTracking();
        Sanctum::actingAs($this->teacher);
        $url = "/api/assignments/{$this->assignment->id}/live/{$this->alice->id}";
        $before = $this->work->fresh();

        // Aucune route d'écriture n'existe sur l'instantané.
        foreach (['putJson', 'patchJson', 'deleteJson'] as $method) {
            $status = $this->$method($url, ['content' => '{"pirate":1}'])->status();
            $this->assertContains($status, [404, 405], "{$method} devrait être refusé");
        }

        $this->getJson($url)->assertOk();

        $after = $this->work->fresh();
        $this->assertSame($before->content, $after->content);
        $this->assertSame($before->name, $after->name);
        // La date d'activité de l'élève n'est pas faussée par l'observation.
        $this->assertTrue($before->updated_at->equalTo($after->updated_at));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function debugModes(): array
    {
        return ['debug désactivé' => ['0'], 'debug activé' => ['1']];
    }

    #[DataProvider('debugModes')]
    public function test_unreachable_assignments_look_alike(string $debug): void
    {
        config(['app.debug' => (bool) $debug]);
        $foreign = Assignment::factory()->published()->create();

        Sanctum::actingAs($this->teacher);

        $targets = [
            'devoir d\'un autre prof' => $foreign->id,
            'ULID inexistant' => (string) Str::ulid(),
            'id mal formé' => 'pas-un-id',
        ];

        foreach ([
            ['GET', '/api/assignments/%s/live'],
            ['GET', '/api/assignments/%s/live/'.$this->alice->id],
            ['POST', '/api/assignments/%s/live-tracking'],
        ] as [$method, $pattern]) {
            $bodies = [];

            foreach ($targets as $label => $id) {
                $response = $this->json($method, sprintf($pattern, $id));
                $this->assertSame(404, $response->status(), "{$method} {$pattern} {$label}");
                $bodies[$label] = $response->getContent();
            }

            $this->assertCount(1, array_unique($bodies), "{$method} {$pattern} : ".json_encode($bodies));
            $this->assertSame('{"message":"Ressource introuvable."}', reset($bodies));
        }
    }

    public function test_every_route_requires_a_token(): void
    {
        $this->enableTracking();
        $id = $this->assignment->id;

        foreach ([
            ['POST', "/api/assignments/{$id}/live-tracking"],
            ['DELETE', "/api/assignments/{$id}/live-tracking"],
            ['GET', "/api/assignments/{$id}/live"],
            ['GET', "/api/assignments/{$id}/live/{$this->alice->id}"],
        ] as [$method, $uri]) {
            $this->json($method, $uri)->assertUnauthorized();
        }

        $this->assertNull($this->work->fresh()->last_observed_at);
    }
}
