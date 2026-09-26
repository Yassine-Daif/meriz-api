<?php

namespace Tests\Feature\Assignments;

use App\Actions\Assignments\StartAssignmentWork;
use App\Actions\Documents\CreateDocument;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Commencer un devoir crée toujours un travail rattaché, même sans base.
 * C'est ce rattachement qui rend l'élève observable.
 */
class StartWorkTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->create(['name' => 'Martin', 'first_name' => 'Alice']);
        $this->classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $this->classroom->members()->attach($this->student->id);
    }

    private function assignment(bool $withBase = false): Assignment
    {
        $factory = Assignment::factory()->for($this->classroom)->published();

        return ($withBase ? $factory->withBase('{"base":"de depart"}') : $factory)->create();
    }

    public function test_starting_an_assignment_without_base_creates_an_attached_work(): void
    {
        $assignment = $this->assignment();
        Sanctum::actingAs($this->student);

        $response = $this->postJson("/api/assignments/{$assignment->id}/start")
            ->assertCreated()
            ->assertJsonPath('data.assignment_id', $assignment->id)
            ->assertJsonPath('data.content', StartAssignmentWork::EMPTY_CONTENT);

        $document = Document::findOrFail($response->json('data.id'));
        $this->assertSame($this->student->id, $document->user_id);
        $this->assertSame($assignment->id, $document->assignment_id);
    }

    public function test_the_student_becomes_visible_and_observable_at_once(): void
    {
        $assignment = $this->assignment();

        Sanctum::actingAs($this->student);
        $documentId = $this->postJson("/api/assignments/{$assignment->id}/start")->assertCreated()->json('data.id');

        // Le prof voit tout de suite que l'élève a commencé.
        Sanctum::actingAs($this->teacher);
        $this->postJson("/api/assignments/{$assignment->id}/live-tracking")->assertOk();

        $entry = collect($this->getJson("/api/assignments/{$assignment->id}/live")->assertOk()->json('data'))
            ->firstWhere('student.id', $this->student->id);

        $this->assertTrue($entry['has_started']);
        $this->assertNotNull($entry['last_activity_at']);

        // Et il est observable : l'instantané répond.
        $this->getJson("/api/assignments/{$assignment->id}/live/{$this->student->id}")
            ->assertOk()
            ->assertJsonPath('data.document_id', $documentId)
            ->assertJsonPath('data.content', StartAssignmentWork::EMPTY_CONTENT);
    }

    public function test_the_first_real_save_is_observed_too(): void
    {
        $assignment = $this->assignment();

        Sanctum::actingAs($this->student);
        $documentId = $this->postJson("/api/assignments/{$assignment->id}/start")->assertCreated()->json('data.id');
        $this->patchJson("/api/documents/{$documentId}", ['content' => '{"MON_TRAVAIL":1}'])->assertOk();

        Sanctum::actingAs($this->teacher);
        $this->postJson("/api/assignments/{$assignment->id}/live-tracking")->assertOk();

        $this->getJson("/api/assignments/{$assignment->id}/live/{$this->student->id}")
            ->assertOk()
            ->assertJsonPath('data.content', '{"MON_TRAVAIL":1}');
    }

    public function test_state_becomes_in_progress_in_the_students_dashboard(): void
    {
        $assignment = $this->assignment();
        Sanctum::actingAs($this->student);

        $this->getJson('/api/overview/my-assignments')->assertJsonPath('data.0.state', 'todo');

        $documentId = $this->postJson("/api/assignments/{$assignment->id}/start")->assertCreated()->json('data.id');

        $this->getJson('/api/overview/my-assignments')
            ->assertJsonPath('data.0.state', 'in_progress')
            ->assertJsonPath('data.0.document_id', $documentId);
    }

    public function test_with_a_base_the_content_is_copied_as_before(): void
    {
        $assignment = $this->assignment(withBase: true);
        Sanctum::actingAs($this->student);

        $this->postJson("/api/assignments/{$assignment->id}/start")
            ->assertCreated()
            ->assertJsonPath('data.content', '{"base":"de depart"}')
            ->assertJsonPath('data.assignment_id', $assignment->id);
    }

    public function test_starting_twice_does_not_duplicate(): void
    {
        $assignment = $this->assignment();
        Sanctum::actingAs($this->student);

        $first = $this->postJson("/api/assignments/{$assignment->id}/start")->assertCreated()->json('data.id');

        // Le travail avance…
        $this->patchJson("/api/documents/{$first}", ['content' => '{"etape":2}'])->assertOk();

        // … et recommencer rend le même document, sans écraser le travail.
        $this->postJson("/api/assignments/{$assignment->id}/start")
            ->assertOk()
            ->assertJsonPath('data.id', $first)
            ->assertJsonPath('data.content', '{"etape":2}');

        $this->assertDatabaseCount('documents', 1);
    }

    public function test_the_client_cannot_choose_the_attachment(): void
    {
        $assignment = $this->assignment();
        $other = Assignment::factory()->for($this->classroom)->published()->create();
        Sanctum::actingAs($this->student);

        $id = $this->postJson("/api/assignments/{$assignment->id}/start", [
            'assignment_id' => $other->id,
            'user_id' => $this->teacher->id,
            'content' => '{"impose":1}',
        ])->assertCreated()->json('data.id');

        $document = Document::findOrFail($id);
        $this->assertSame($assignment->id, $document->assignment_id);
        $this->assertSame($this->student->id, $document->user_id);
        $this->assertSame(StartAssignmentWork::EMPTY_CONTENT, $document->content);
    }

    public function test_the_old_copy_route_behaves_the_same(): void
    {
        $assignment = $this->assignment();
        Sanctum::actingAs($this->student);

        $id = $this->postJson("/api/assignments/{$assignment->id}/copy")
            ->assertCreated()
            ->assertJsonPath('data.assignment_id', $assignment->id)
            ->json('data.id');

        $this->postJson("/api/assignments/{$assignment->id}/start")
            ->assertOk()
            ->assertJsonPath('data.id', $id);
    }

    public function test_quota_still_applies(): void
    {
        config(['documents.max_per_user' => 1]);
        Document::factory()->for($this->student)->create();
        $assignment = $this->assignment();
        Sanctum::actingAs($this->student);

        $this->postJson("/api/assignments/{$assignment->id}/start")
            ->assertUnprocessable()
            ->assertJsonPath('errors.content.0', CreateDocument::QUOTA_MESSAGE);
    }

    public function test_drafts_and_foreign_assignments_stay_out_of_reach(): void
    {
        $draft = Assignment::factory()->for($this->classroom)->create();
        $foreign = Assignment::factory()->published()->create();

        Sanctum::actingAs($this->student);
        $this->postJson("/api/assignments/{$draft->id}/start")->assertNotFound();
        $this->postJson("/api/assignments/{$foreign->id}/start")->assertNotFound();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/assignments/{$this->assignment()->id}/start")->assertNotFound();

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_starting_requires_a_token(): void
    {
        $assignment = $this->assignment();

        $this->postJson("/api/assignments/{$assignment->id}/start")->assertUnauthorized();
        $this->assertDatabaseCount('documents', 0);
    }
}
