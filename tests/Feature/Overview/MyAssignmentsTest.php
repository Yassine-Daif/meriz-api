<?php

namespace Tests\Feature\Overview;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Document;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyAssignmentsTest extends TestCase
{
    use RefreshDatabase;

    private const SOLUTION_MARKER = 'CORRIGE-3e8f-jamais-ici';

    private User $student;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create();
        $this->classroom = Classroom::factory()->create(['name' => 'Première SNT']);
        $this->classroom->members()->attach($this->student->id);

        Sanctum::actingAs($this->student);
    }

    private function assignment(array $attributes = [], ?Classroom $classroom = null): Assignment
    {
        return Assignment::factory()->for($classroom ?? $this->classroom)->published()
            ->withBase()
            ->withSolution('{"corrige":"'.self::SOLUTION_MARKER.'"}')
            ->create($attributes);
    }

    public function test_student_sees_published_assignments_of_all_their_classrooms(): void
    {
        $first = $this->assignment(['title' => 'Devoir 1', 'due_at' => now()->addDay()]);

        $second = Classroom::factory()->create();
        $second->members()->attach($this->student->id);
        $fromSecond = $this->assignment(['title' => 'Devoir 2', 'due_at' => now()->addWeek()], $second);

        // Classe étrangère bien peuplée : c'est le filtre sur mes classes,
        // et pas l'absence de membres, qui doit l'écarter.
        $foreignClassroom = Classroom::factory()->create();
        $foreignClassroom->members()->attach(User::factory()->create()->id);
        $this->assignment(['title' => 'Devoir étranger'], $foreignClassroom);
        Assignment::factory()->for($this->classroom)->create(['title' => 'Brouillon']);

        $response = $this->getJson('/api/overview/my-assignments')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$first->id, $fromSecond->id])
            ->assertJsonPath('data.0.classroom.name', 'Première SNT')
            ->assertJsonPath('data.0.state', 'todo')
            ->assertJsonPath('data.0.submission', null)
            ->assertJsonPath('data.0.document_id', null)
            ->assertJsonPath('data.0.has_base', true);

        $response->assertDontSee('Devoir étranger')->assertDontSee('Brouillon');
    }

    public function test_the_four_states(): void
    {
        $todo = $this->assignment(['due_at' => now()->addDay()]);
        $inProgress = $this->assignment(['due_at' => now()->addDays(2)]);
        $submitted = $this->assignment(['due_at' => now()->addDays(3)]);
        $graded = $this->assignment(['due_at' => now()->addDays(4)]);

        // « En cours » : la copie de la base crée un document lié.
        $documentId = $this->postJson("/api/assignments/{$inProgress->id}/copy")->assertCreated()->json('data.id');

        $this->putJson("/api/assignments/{$submitted->id}/submission", ['content' => '{"v":1}'])->assertCreated();

        $gradedSubmission = Submission::factory()->for($graded)->for($this->student, 'student')
            ->graded('17/20', 'Très bien.')->create();

        $data = collect($this->getJson('/api/overview/my-assignments')->assertOk()->json('data'))
            ->keyBy('id');

        $this->assertSame('todo', $data[$todo->id]['state']);
        $this->assertSame('in_progress', $data[$inProgress->id]['state']);
        $this->assertSame($documentId, $data[$inProgress->id]['document_id']);
        $this->assertSame('submitted', $data[$submitted->id]['state']);
        $this->assertSame('graded', $data[$graded->id]['state']);

        $this->assertSame('17/20', $data[$graded->id]['submission']['grade']);
        $this->assertSame('Très bien.', $data[$graded->id]['submission']['feedback']);
        $this->assertSame($gradedSubmission->id, $data[$graded->id]['submission']['id']);
    }

    public function test_another_students_work_never_changes_my_state(): void
    {
        $assignment = $this->assignment();
        $other = User::factory()->create();
        $this->classroom->members()->attach($other->id);

        Submission::factory()->for($assignment)->for($other, 'student')->create();
        Document::factory()->for($other)->forAssignment($assignment)->create();

        $this->getJson('/api/overview/my-assignments')
            ->assertJsonPath('data.0.state', 'todo')
            ->assertJsonPath('data.0.submission', null)
            ->assertJsonPath('data.0.document_id', null);
    }

    public function test_solution_never_appears_even_once_released(): void
    {
        $assignment = $this->assignment();
        $assignment->forceFill(['solution_released_at' => now()])->save();

        $body = $this->getJson('/api/overview/my-assignments')->assertOk()->getContent();

        $this->assertStringNotContainsString(self::SOLUTION_MARKER, $body);
        $this->assertArrayNotHasKey('solution_content', $this->getJson('/api/overview/my-assignments')->json('data.0'));
    }

    public function test_sorting_and_overdue_flag(): void
    {
        $noDeadline = $this->assignment(['due_at' => null]);
        $later = $this->assignment(['due_at' => now()->addWeek()]);
        $overdue = $this->assignment(['due_at' => now()->subDay()]);

        $this->getJson('/api/overview/my-assignments')
            ->assertJsonPath('data.*.id', [$overdue->id, $later->id, $noDeadline->id])
            ->assertJsonPath('data.0.is_overdue', true)
            ->assertJsonPath('data.1.is_overdue', false)
            ->assertJsonPath('data.2.is_overdue', false);
    }

    public function test_query_count_does_not_grow_with_the_data(): void
    {
        $this->assignment();
        $small = $this->countQueries(fn () => $this->getJson('/api/overview/my-assignments')->assertJsonCount(1, 'data'));

        // Deux classes de plus, quatre devoirs, avec rendus et documents liés.
        foreach (range(1, 2) as $c) {
            $classroom = Classroom::factory()->create();
            $classroom->members()->attach($this->student->id);

            foreach (range(1, 2) as $a) {
                $assignment = $this->assignment([], $classroom);
                Submission::factory()->for($assignment)->for($this->student, 'student')->create();
                Document::factory()->for($this->student)->forAssignment($assignment)->create();
            }
        }

        $large = $this->countQueries(fn () => $this->getJson('/api/overview/my-assignments')->assertJsonCount(5, 'data'));

        $this->assertSame($small, $large, "Requêtes : {$small} pour 1 devoir, {$large} pour 5.");
        $this->assertLessThanOrEqual(5, $large);
    }

    public function test_route_requires_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/overview/my-assignments')->assertUnauthorized();
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
