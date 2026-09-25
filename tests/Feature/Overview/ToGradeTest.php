<?php

namespace Tests\Feature\Overview;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ToGradeTest extends TestCase
{
    use RefreshDatabase;

    private const SOLUTION_MARKER = 'CORRIGE-9a2b-jamais-ici';

    private const WORK_MARKER = 'TRAVAIL-5c1d-jamais-ici';

    private User $teacher;

    private Classroom $classroom;

    private Assignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
        $this->classroom = Classroom::factory()->for($this->teacher, 'teacher')->create(['name' => 'Terminale NSI']);
        $this->assignment = Assignment::factory()->for($this->classroom)->published()
            ->withSolution('{"corrige":"'.self::SOLUTION_MARKER.'"}')
            ->create(['title' => 'MCD bibliothèque', 'due_at' => now()->subDay()]);
    }

    private function submissionFrom(User $student, ?Assignment $assignment = null): Submission
    {
        $assignment ??= $this->assignment;
        $assignment->classroom->members()->syncWithoutDetaching($student->id);

        return Submission::factory()->for($assignment)->for($student, 'student')
            ->create(['content' => '{"travail":"'.self::WORK_MARKER.'"}']);
    }

    public function test_teacher_only_sees_submissions_of_their_own_classrooms(): void
    {
        $mine = $this->submissionFrom(User::factory()->create());

        $otherTeacherClass = Classroom::factory()->create();
        $foreign = Submission::factory()
            ->for(Assignment::factory()->for($otherTeacherClass)->published())
            ->for(User::factory()->create(), 'student')
            ->create();

        Sanctum::actingAs($this->teacher);

        $response = $this->getJson('/api/overview/to-grade')
            ->assertOk()
            ->assertJsonPath('data.*.submission_id', [$mine->id])
            ->assertJsonPath('data.0.classroom.name', 'Terminale NSI')
            ->assertJsonPath('data.0.assignment.title', 'MCD bibliothèque')
            ->assertJsonPath('data.0.is_late', true);

        $this->assertStringNotContainsString($foreign->id, $response->getContent());
    }

    public function test_graded_submissions_disappear_and_come_back_when_the_grade_is_removed(): void
    {
        $submission = $this->submissionFrom(User::factory()->create());
        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/overview/to-grade')->assertJsonCount(1, 'data');

        $this->postJson("/api/submissions/{$submission->id}/grade", ['grade' => '15/20'])->assertOk();
        $this->getJson('/api/overview/to-grade')->assertJsonPath('data', []);

        $this->deleteJson("/api/submissions/{$submission->id}/grade")->assertOk();
        $this->getJson('/api/overview/to-grade')->assertJsonCount(1, 'data');
    }

    public function test_submission_on_an_unpublished_assignment_stays_in_the_queue(): void
    {
        // Un devoir dépublié après coup : le travail remis attend toujours
        // une note, et le prof voit ses propres brouillons.
        $draft = Assignment::factory()->for($this->classroom)->create();
        $submission = $this->submissionFrom(User::factory()->create(), $draft);

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/overview/to-grade')
            ->assertJsonPath('data.*.submission_id', [$submission->id]);
    }

    public function test_oldest_submissions_come_first(): void
    {
        $recent = $this->submissionFrom(User::factory()->create());
        $recent->forceFill(['submitted_at' => now()->subHour()])->save();

        $oldest = $this->submissionFrom(User::factory()->create());
        $oldest->forceFill(['submitted_at' => now()->subWeek()])->save();

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/overview/to-grade')
            ->assertJsonPath('data.*.submission_id', [$oldest->id, $recent->id]);
    }

    public function test_response_carries_no_content_no_solution_and_no_email(): void
    {
        $student = User::factory()->create(['email' => 'eleve.secret@gmail.com']);
        $this->submissionFrom($student);

        Sanctum::actingAs($this->teacher);
        $body = $this->getJson('/api/overview/to-grade')->assertOk()->getContent();

        $this->assertStringNotContainsString(self::WORK_MARKER, $body);
        $this->assertStringNotContainsString(self::SOLUTION_MARKER, $body);
        $this->assertStringNotContainsString('eleve.secret@gmail.com', $body);
        $this->assertStringNotContainsString('"email"', $body);

        $entry = $this->getJson('/api/overview/to-grade')->json('data.0');
        $this->assertSame(
            ['submission_id', 'submitted_at', 'is_late', 'student', 'assignment', 'classroom'],
            array_keys($entry),
        );
        $this->assertSame($student->first_name, $entry['student']['first_name']);
    }

    public function test_student_sees_an_empty_queue(): void
    {
        $student = User::factory()->create();
        $this->submissionFrom($student);

        Sanctum::actingAs($student);

        $this->getJson('/api/overview/to-grade')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_pagination_is_bounded(): void
    {
        foreach (range(1, 3) as $i) {
            $this->submissionFrom(User::factory()->create());
        }

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/overview/to-grade?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/overview/to-grade?per_page=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_query_count_does_not_grow_with_the_data(): void
    {
        $this->submissionFrom(User::factory()->create());

        Sanctum::actingAs($this->teacher);
        $small = $this->countQueries(fn () => $this->getJson('/api/overview/to-grade')->assertJsonCount(1, 'data'));

        // Trois classes, six devoirs, douze rendus de plus.
        foreach (range(1, 3) as $c) {
            $classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();

            foreach (range(1, 2) as $a) {
                $assignment = Assignment::factory()->for($classroom)->published()->create();

                foreach (range(1, 2) as $s) {
                    $this->submissionFrom(User::factory()->create(), $assignment);
                }
            }
        }

        $large = $this->countQueries(fn () => $this->getJson('/api/overview/to-grade')->assertJsonCount(13, 'data'));

        $this->assertSame($small, $large, "Requêtes : {$small} pour 1 rendu, {$large} pour 13.");
        $this->assertLessThanOrEqual(6, $large);
    }

    public function test_route_requires_a_token(): void
    {
        $this->getJson('/api/overview/to-grade')->assertUnauthorized();
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
