<?php

namespace Tests\Feature\Submissions;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubmissionGradingTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Assignment $assignment;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->create(['name' => 'Durand', 'first_name' => 'Léa']);
        $classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $classroom->members()->attach($this->student->id);

        $this->assignment = Assignment::factory()->for($classroom)->published()
            ->create(['due_at' => now()->subDay()]);
        $this->submission = Submission::factory()->for($this->assignment)->for($this->student, 'student')
            ->create(['content' => '{"travail":1}']);
    }

    public function test_teacher_lists_submissions_without_their_content(): void
    {
        $late = Submission::factory()->for($this->assignment)->late()
            ->for(User::factory()->create(), 'student')->create();

        Sanctum::actingAs($this->teacher);

        $response = $this->getJson("/api/assignments/{$this->assignment->id}/submissions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $late->id)
            ->assertJsonPath('data.0.is_late', true)
            ->assertJsonPath('data.1.student.first_name', 'Léa')
            ->assertJsonPath('data.1.status', 'submitted');

        foreach ($response->json('data') as $entry) {
            $this->assertArrayNotHasKey('content', $entry);
        }
    }

    public function test_teacher_opens_and_grades_a_submission(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->getJson("/api/submissions/{$this->submission->id}")
            ->assertOk()
            ->assertJsonPath('data.content', '{"travail":1}');

        $this->postJson("/api/submissions/{$this->submission->id}/grade", [
            'grade' => '16/20',
            'feedback' => 'Bonne modélisation, attention aux cardinalités.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'graded')
            ->assertJsonPath('data.grade', '16/20')
            ->assertJsonPath('data.feedback', 'Bonne modélisation, attention aux cardinalités.');

        $this->assertNotNull($this->submission->fresh()->graded_at);
    }

    public function test_student_sees_their_grade_and_feedback(): void
    {
        Sanctum::actingAs($this->teacher);
        $this->postJson("/api/submissions/{$this->submission->id}/grade", [
            'grade' => 'Acquis',
            'feedback' => 'Revois les associations.',
        ])->assertOk();

        Sanctum::actingAs($this->student);

        $this->getJson("/api/assignments/{$this->assignment->id}/submission")
            ->assertOk()
            ->assertJsonPath('data.status', 'graded')
            ->assertJsonPath('data.grade', 'Acquis')
            ->assertJsonPath('data.feedback', 'Revois les associations.');

        $this->getJson("/api/submissions/{$this->submission->id}")
            ->assertOk()
            ->assertJsonPath('data.grade', 'Acquis');
    }

    public function test_grade_without_feedback_and_regrading(): void
    {
        Sanctum::actingAs($this->teacher);
        $url = "/api/submissions/{$this->submission->id}/grade";

        $this->postJson($url, ['grade' => '12/20'])->assertOk()->assertJsonPath('data.feedback', null);
        $this->postJson($url, ['grade' => '15/20', 'feedback' => 'Après relecture.'])
            ->assertOk()
            ->assertJsonPath('data.grade', '15/20')
            ->assertJsonPath('data.feedback', 'Après relecture.');

        $this->assertSame('15/20', $this->submission->fresh()->grade);
    }

    public function test_removing_the_grade_unlocks_the_submission(): void
    {
        Sanctum::actingAs($this->teacher);
        $this->postJson("/api/submissions/{$this->submission->id}/grade", ['grade' => '10/20', 'feedback' => 'Incomplet.'])->assertOk();

        $this->deleteJson("/api/submissions/{$this->submission->id}/grade")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.grade', null)
            ->assertJsonPath('data.feedback', null)
            ->assertJsonPath('data.graded_at', null);

        $fresh = $this->submission->fresh();
        $this->assertFalse($fresh->isGraded());
        $this->assertNull($fresh->graded_at);
    }

    public function test_grade_is_validated(): void
    {
        Sanctum::actingAs($this->teacher);
        $url = "/api/submissions/{$this->submission->id}/grade";

        $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['grade']);
        $this->postJson($url, ['grade' => ''])->assertUnprocessable()->assertJsonValidationErrors(['grade']);
        $this->postJson($url, ['grade' => str_repeat('a', 51)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['grade']);
        $this->postJson($url, ['grade' => '12/20', 'feedback' => str_repeat('a', 2001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['feedback']);

        $this->assertNull($this->submission->fresh()->grade);
    }
}
