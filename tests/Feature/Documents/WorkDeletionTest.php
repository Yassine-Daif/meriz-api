<?php

namespace Tests\Feature\Documents;

use App\Actions\Assignments\StartAssignmentWork;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Un élève supprime son travail de devoir depuis sa liste de documents.
 *
 * Le rendu n'a aucune clé étrangère vers le document : c'est une copie
 * autonome. Cette garantie est structurelle, ces tests la verrouillent,
 * pour qu'une migration ou un hook posé plus tard ne la casse pas en
 * silence. La base et le corrigé du prof, eux, vivent sur le devoir.
 */
class WorkDeletionTest extends TestCase
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

    private function assignment(bool $withBase = false, bool $withSolution = false): Assignment
    {
        $factory = Assignment::factory()->for($this->classroom)->published();

        if ($withBase) {
            $factory = $factory->withBase('{"base":"de depart"}');
        }

        if ($withSolution) {
            $factory = $factory->withSolution('{"corrige":"du prof"}');
        }

        return $factory->create();
    }

    /** Commence le travail, le rend, et renvoie l'identifiant du document. */
    private function startAndSubmit(Assignment $assignment): string
    {
        Sanctum::actingAs($this->student);

        $documentId = $this->postJson("/api/assignments/{$assignment->id}/start")
            ->assertCreated()
            ->json('data.id');

        // Premier dépôt : 201. Un dépôt suivant répondrait 200.
        $this->putJson("/api/assignments/{$assignment->id}/submission", ['content' => '{"travail":1}'])
            ->assertSuccessful();

        return $documentId;
    }

    private function grade(Assignment $assignment): string
    {
        $submissionId = $assignment->submissions()->firstOrFail()->id;

        Sanctum::actingAs($this->teacher);
        $this->postJson("/api/submissions/{$submissionId}/grade", [
            'grade' => '17/20',
            'feedback' => 'Très bien.',
        ])->assertOk();

        return $submissionId;
    }

    public function test_the_personal_list_still_shows_the_work_with_its_assignment_id(): void
    {
        $assignment = $this->assignment();
        $documentId = $this->startAndSubmit($assignment);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/documents')
            ->assertOk()
            ->assertJsonPath('data.0.id', $documentId)
            ->assertJsonPath('data.0.assignment_id', $assignment->id)
            ->assertJsonPath('data.0.group_id', null);
    }

    public function test_deleting_the_work_keeps_the_submission_its_grade_and_its_feedback(): void
    {
        $assignment = $this->assignment();
        $documentId = $this->startAndSubmit($assignment);
        $submissionId = $this->grade($assignment);

        Sanctum::actingAs($this->student);
        $this->deleteJson("/api/documents/{$documentId}")->assertNoContent();

        $this->assertDatabaseMissing('documents', ['id' => $documentId]);
        $this->assertDatabaseHas('submissions', [
            'id' => $submissionId,
            'assignment_id' => $assignment->id,
            'user_id' => $this->student->id,
            'content' => '{"travail":1}',
            'grade' => '17/20',
            'feedback' => 'Très bien.',
        ]);
        $this->assertNotNull($assignment->submissions()->firstOrFail()->graded_at);
    }

    public function test_the_student_still_reads_their_graded_submission_after_the_deletion(): void
    {
        $assignment = $this->assignment();
        $documentId = $this->startAndSubmit($assignment);
        $this->grade($assignment);

        Sanctum::actingAs($this->student);
        $this->deleteJson("/api/documents/{$documentId}")->assertNoContent();

        $this->getJson("/api/assignments/{$assignment->id}/submission")
            ->assertOk()
            ->assertJsonPath('data.status', 'graded')
            ->assertJsonPath('data.grade', '17/20')
            ->assertJsonPath('data.feedback', 'Très bien.')
            ->assertJsonPath('data.content', '{"travail":1}');
    }

    public function test_the_teacher_still_opens_the_submission_after_the_deletion(): void
    {
        $assignment = $this->assignment();
        $documentId = $this->startAndSubmit($assignment);
        $submissionId = $this->grade($assignment);

        Sanctum::actingAs($this->student);
        $this->deleteJson("/api/documents/{$documentId}")->assertNoContent();

        Sanctum::actingAs($this->teacher);
        $this->getJson("/api/submissions/{$submissionId}")
            ->assertOk()
            ->assertJsonPath('data.content', '{"travail":1}')
            ->assertJsonPath('data.grade', '17/20');

        $this->getJson("/api/assignments/{$assignment->id}/submissions")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_my_assignments_forgets_the_document_but_keeps_the_graded_state(): void
    {
        $assignment = $this->assignment();
        $documentId = $this->startAndSubmit($assignment);
        $this->grade($assignment);

        Sanctum::actingAs($this->student);
        $this->deleteJson("/api/documents/{$documentId}")->assertNoContent();

        $this->getJson('/api/overview/my-assignments')
            ->assertOk()
            ->assertJsonPath('data.0.id', $assignment->id)
            ->assertJsonPath('data.0.document_id', null)
            ->assertJsonPath('data.0.state', 'graded')
            ->assertJsonPath('data.0.submission.grade', '17/20');
    }

    public function test_restarting_after_a_deletion_creates_a_brand_new_empty_work(): void
    {
        $assignment = $this->assignment();
        $documentId = $this->startAndSubmit($assignment);

        Sanctum::actingAs($this->student);
        $this->deleteJson("/api/documents/{$documentId}")->assertNoContent();

        // Le travail supprimé n'est pas rendu : page vide, nouvel identifiant.
        $again = $this->postJson("/api/assignments/{$assignment->id}/start")
            ->assertCreated()
            ->assertJsonPath('data.content', StartAssignmentWork::EMPTY_CONTENT);

        $this->assertNotSame($documentId, $again->json('data.id'));
        $this->assertDatabaseCount('documents', 1);
    }

    public function test_restarting_after_a_deletion_copies_the_base_again_when_there_is_one(): void
    {
        $assignment = $this->assignment(withBase: true);
        $documentId = $this->startAndSubmit($assignment);

        Sanctum::actingAs($this->student);
        $this->deleteJson("/api/documents/{$documentId}")->assertNoContent();

        // La base du prof, pas le rendu de l'élève.
        $this->postJson("/api/assignments/{$assignment->id}/start")
            ->assertCreated()
            ->assertJsonPath('data.content', '{"base":"de depart"}');
    }

    public function test_deleting_the_work_never_touches_the_base_or_the_solution_of_the_teacher(): void
    {
        $assignment = $this->assignment(withBase: true, withSolution: true);
        $documentId = $this->startAndSubmit($assignment);

        Sanctum::actingAs($this->student);
        $this->deleteJson("/api/documents/{$documentId}")->assertNoContent();

        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'base_content' => '{"base":"de depart"}',
            'solution_content' => '{"corrige":"du prof"}',
        ]);
    }

    public function test_a_student_has_no_route_to_delete_a_submission(): void
    {
        $assignment = $this->assignment();
        $this->startAndSubmit($assignment);
        $submissionId = $assignment->submissions()->firstOrFail()->id;

        Sanctum::actingAs($this->student);

        // Verrou structurel : /submissions/{id} n'accepte que la lecture,
        // d'où 405. Supprimer un rendu n'est offert à personne.
        $this->deleteJson("/api/submissions/{$submissionId}")->assertStatus(405);
        $this->deleteJson("/api/assignments/{$assignment->id}/submission")->assertStatus(405);
        $this->assertDatabaseHas('submissions', ['id' => $submissionId]);
    }

    public function test_a_student_cannot_delete_the_work_of_another_student(): void
    {
        $other = User::factory()->create();
        $this->classroom->members()->attach($other->id);
        $assignment = $this->assignment();

        Sanctum::actingAs($other);
        $otherWork = $this->postJson("/api/assignments/{$assignment->id}/start")
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($this->student);
        $this->deleteJson("/api/documents/{$otherWork}")->assertNotFound();

        $this->assertDatabaseHas('documents', ['id' => $otherWork, 'user_id' => $other->id]);
        $this->assertSame($assignment->id, Document::findOrFail($otherWork)->assignment_id);
    }
}
