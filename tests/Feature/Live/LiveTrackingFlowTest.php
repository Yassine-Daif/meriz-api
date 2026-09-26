<?php

namespace Tests\Feature\Live;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Document;
use App\Models\Submission;
use App\Models\User;
use App\Policies\AssignmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LiveTrackingFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $alice;

    private User $bob;

    private Classroom $classroom;

    private Assignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
        $this->alice = User::factory()->create(['name' => 'Martin', 'first_name' => 'Alice']);
        $this->bob = User::factory()->create(['name' => 'Durand', 'first_name' => 'Bob']);

        $this->classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $this->classroom->members()->attach([$this->alice->id, $this->bob->id]);

        $this->assignment = Assignment::factory()->for($this->classroom)->published()->withBase()->create();
    }

    private function enable(): void
    {
        Sanctum::actingAs($this->teacher);
        $this->postJson("/api/assignments/{$this->assignment->id}/live-tracking")
            ->assertOk()
            ->assertJsonPath('data.live_tracking', true);
    }

    public function test_teacher_enables_then_disables_tracking(): void
    {
        $this->enable();
        $fresh = $this->assignment->fresh();
        $this->assertTrue($fresh->liveTrackingEnabled());
        $this->assertNotNull($fresh->live_tracking_enabled_at);

        $this->deleteJson("/api/assignments/{$this->assignment->id}/live-tracking")
            ->assertOk()
            ->assertJsonPath('data.live_tracking', false)
            ->assertJsonPath('data.live_tracking_enabled_at', null);

        // L'accès se referme aussitôt.
        $this->getJson("/api/assignments/{$this->assignment->id}/live")
            ->assertForbidden()
            ->assertJsonPath('message', AssignmentPolicy::TRACKING_OFF_MESSAGE);
    }

    public function test_student_sees_the_flag_in_the_assignment(): void
    {
        Sanctum::actingAs($this->alice);
        $this->getJson("/api/assignments/{$this->assignment->id}")
            ->assertOk()
            ->assertJsonPath('data.live_tracking', false)
            ->assertJsonPath('data.live_tracking_enabled_at', null);

        $this->enable();

        Sanctum::actingAs($this->alice);
        $response = $this->getJson("/api/assignments/{$this->assignment->id}")->assertOk();
        $response->assertJsonPath('data.live_tracking', true);
        $this->assertNotNull($response->json('data.live_tracking_enabled_at'));
    }

    public function test_list_shows_who_started_and_when(): void
    {
        // Alice a commencé, Bob non.
        Sanctum::actingAs($this->alice);
        $this->postJson("/api/assignments/{$this->assignment->id}/copy")->assertCreated();

        $this->enable();

        $data = collect($this->getJson("/api/assignments/{$this->assignment->id}/live")->assertOk()->json('data'))
            ->keyBy('student.id');

        $this->assertTrue($data[$this->alice->id]['has_started']);
        $this->assertNotNull($data[$this->alice->id]['last_activity_at']);
        $this->assertNull($data[$this->alice->id]['last_observed_at']);
        $this->assertNull($data[$this->alice->id]['submission_status']);

        $this->assertFalse($data[$this->bob->id]['has_started']);
        $this->assertNull($data[$this->bob->id]['last_activity_at']);

        // Nom et prénom présents, aucun email.
        $this->assertSame('Alice', $data[$this->alice->id]['student']['first_name']);
        $this->assertStringNotContainsString('"email"', $this->getJson("/api/assignments/{$this->assignment->id}/live")->getContent());
    }

    public function test_list_shows_the_submission_state(): void
    {
        Submission::factory()->for($this->assignment)->for($this->alice, 'student')->graded()->create();
        Document::factory()->for($this->alice)->forAssignment($this->assignment)->create();

        $this->enable();

        $data = collect($this->getJson("/api/assignments/{$this->assignment->id}/live")->json('data'))
            ->keyBy('student.id');

        $this->assertSame('graded', $data[$this->alice->id]['submission_status']);
        $this->assertNull($data[$this->bob->id]['submission_status']);
    }

    public function test_snapshot_follows_the_students_work(): void
    {
        Sanctum::actingAs($this->alice);
        $documentId = $this->postJson("/api/assignments/{$this->assignment->id}/copy")->assertCreated()->json('data.id');

        $this->enable();
        $url = "/api/assignments/{$this->assignment->id}/live/{$this->alice->id}";

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.document_id', $documentId)
            ->assertJsonPath('data.student.first_name', 'Alice');

        // L'élève avance…
        $content = '{"model":{},"etape":2,"zoom":1.0,"t":"é\\u00e9"}';
        Sanctum::actingAs($this->alice);
        $this->patchJson("/api/documents/{$documentId}", ['content' => $content])->assertOk();

        // … et le prof voit la version du moment, octet pour octet.
        Sanctum::actingAs($this->teacher);
        $this->getJson($url)->assertOk()->assertJsonPath('data.content', $content);
    }

    public function test_observing_a_student_who_has_not_started_is_a_404(): void
    {
        $this->enable();

        $this->getJson("/api/assignments/{$this->assignment->id}/live/{$this->bob->id}")->assertNotFound();
    }

    public function test_the_student_sees_that_their_work_was_read(): void
    {
        Sanctum::actingAs($this->alice);
        $documentId = $this->postJson("/api/assignments/{$this->assignment->id}/copy")->assertCreated()->json('data.id');
        $this->getJson("/api/documents/{$documentId}")->assertJsonPath('data.last_observed_at', null);

        $this->enable();
        $observedAt = $this->getJson("/api/assignments/{$this->assignment->id}/live/{$this->alice->id}")
            ->assertOk()
            ->json('data.observed_at');
        $this->assertNotNull($observedAt);

        Sanctum::actingAs($this->alice);

        // Sur son document…
        $this->getJson("/api/documents/{$documentId}")
            ->assertOk()
            ->assertJsonPath('data.last_observed_at', $observedAt);

        // … et dans la liste de ses documents.
        $this->getJson('/api/documents')->assertJsonPath('data.0.last_observed_at', $observedAt);

        // La trace suit aussi le tableau de bord de l'élève.
        $this->getJson("/api/assignments/{$this->assignment->id}")
            ->assertJsonPath('data.live_tracking', true);
    }

    public function test_query_count_does_not_grow_with_the_class_size(): void
    {
        Document::factory()->for($this->alice)->forAssignment($this->assignment)->create();
        $this->enable();

        $small = $this->countQueries(
            fn () => $this->getJson("/api/assignments/{$this->assignment->id}/live")->assertJsonCount(2, 'data')
        );

        // Quinze élèves de plus, dont dix qui ont commencé.
        foreach (range(1, 15) as $i) {
            $student = User::factory()->create();
            $this->classroom->members()->attach($student->id);

            if ($i <= 10) {
                Document::factory()->for($student)->forAssignment($this->assignment)->create();
            }
        }

        $large = $this->countQueries(
            fn () => $this->getJson("/api/assignments/{$this->assignment->id}/live")->assertJsonCount(17, 'data')
        );

        $this->assertSame($small, $large, "Requêtes : {$small} pour 2 élèves, {$large} pour 17.");
        $this->assertLessThanOrEqual(5, $large);
    }

    private function countQueries(callable $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $callback();

        return $count;
    }
}
