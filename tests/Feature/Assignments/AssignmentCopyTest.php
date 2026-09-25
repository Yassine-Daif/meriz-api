<?php

namespace Tests\Feature\Assignments;

use App\Actions\Assignments\CopyAssignmentBase;
use App\Actions\Documents\CreateDocument;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssignmentCopyTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create();
        $this->classroom = Classroom::factory()->create();
        $this->classroom->members()->attach($this->student->id);
        Sanctum::actingAs($this->student);
    }

    public function test_copy_creates_a_document_of_the_student_with_the_exact_base(): void
    {
        $base = "{\"model\":{},\"zoom\":1.0,\n \"t\":\"é\\u00e9\"}";
        $assignment = Assignment::factory()->for($this->classroom)->published()->withBase($base)
            ->withSolution('{"secret":true}')
            ->create(['title' => 'MCD bibliothèque']);

        $response = $this->postJson("/api/assignments/{$assignment->id}/copy")
            ->assertCreated()
            ->assertJsonPath('data.name', 'MCD bibliothèque')
            ->assertJsonPath('data.content', $base);

        $document = Document::findOrFail($response->json('data.id'));
        $this->assertSame($this->student->id, $document->user_id);
        $this->assertSame($base, $document->content);

        // Le document est modifiable comme les autres.
        $this->patchJson("/api/documents/{$document->id}", ['content' => '{"modifie":true}'])->assertOk();
        $this->assertSame($base, $assignment->fresh()->base_content);
    }

    public function test_copy_records_the_link_to_the_assignment(): void
    {
        $assignment = Assignment::factory()->for($this->classroom)->published()->withBase()->create();

        $id = $this->postJson("/api/assignments/{$assignment->id}/copy")
            ->assertCreated()
            ->assertJsonPath('data.assignment_id', $assignment->id)
            ->json('data.id');

        $document = Document::findOrFail($id);
        $this->assertTrue($document->assignment->is($assignment));

        // Supprimer le devoir garde le travail de l'élève, sans rattachement.
        $assignment->delete();
        $this->assertModelExists($document);
        $this->assertNull($document->fresh()->assignment_id);
    }

    public function test_a_plain_document_has_no_assignment(): void
    {
        $this->postJson('/api/documents', ['name' => 'Perso', 'content' => '{}'])
            ->assertCreated()
            ->assertJsonPath('data.assignment_id', null);
    }

    public function test_copy_without_base_is_refused(): void
    {
        $assignment = Assignment::factory()->for($this->classroom)->published()->create();

        $this->postJson("/api/assignments/{$assignment->id}/copy")
            ->assertUnprocessable()
            ->assertJsonPath('errors.base_content.0', CopyAssignmentBase::NO_BASE_MESSAGE);

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_copy_respects_the_document_quota(): void
    {
        config(['documents.max_per_user' => 1]);
        Document::factory()->for($this->student)->create();
        $assignment = Assignment::factory()->for($this->classroom)->published()->withBase()->create();

        $this->postJson("/api/assignments/{$assignment->id}/copy")
            ->assertUnprocessable()
            ->assertJsonPath('errors.content.0', CreateDocument::QUOTA_MESSAGE);
    }

    public function test_teacher_can_copy_their_own_base(): void
    {
        $assignment = Assignment::factory()->for($this->classroom)->withBase()->create();
        $teacher = $this->classroom->teacher;
        Sanctum::actingAs($teacher);

        $id = $this->postJson("/api/assignments/{$assignment->id}/copy")->assertCreated()->json('data.id');

        $this->assertSame($teacher->id, Document::findOrFail($id)->user_id);
    }
}
