<?php

namespace Tests\Feature\Assignments;

use App\Actions\Assignments\ChangeAssignmentState;
use App\Actions\Assignments\CreateAssignment;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\User;
use App\Policies\AssignmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssignmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->teacher = User::factory()->teacher()->create();
        $this->classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        Sanctum::actingAs($this->teacher);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'MCD bibliothèque',
            'instructions' => 'Une bibliothèque prête des livres à ses adhérents…',
            'type' => 'exercise',
            'due_at' => '2026-10-01T18:00:00+02:00',
            'base_content' => '{"model":{},"zoom":1.0}',
            'solution_content' => '{"model":{"entities":["Livre"]}}',
        ], $overrides);
    }

    public function test_teacher_creates_a_draft_assignment(): void
    {
        $response = $this->postJson("/api/classrooms/{$this->classroom->id}/assignments", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.type', 'exercise')
            ->assertJsonPath('data.classroom_id', $this->classroom->id)
            ->assertJsonPath('data.has_base', true)
            ->assertJsonPath('data.has_solution', true)
            ->assertJsonPath('data.solution_released', false)
            ->assertJsonPath('data.base_content', '{"model":{},"zoom":1.0}')
            ->assertJsonPath('data.solution_content', '{"model":{"entities":["Livre"]}}');

        $this->assertSame('2026-10-01T16:00:00+00:00', $response->json('data.due_at'));
    }

    public function test_optional_fields_can_be_omitted(): void
    {
        $this->postJson("/api/classrooms/{$this->classroom->id}/assignments", [
            'title' => 'Minimal',
            'instructions' => 'Consigne',
            'type' => 'exam',
        ])->assertCreated()
            ->assertJsonPath('data.has_base', false)
            ->assertJsonPath('data.has_solution', false)
            ->assertJsonPath('data.has_image', false)
            ->assertJsonPath('data.due_at', null);
    }

    public function test_student_cannot_create_an_assignment(): void
    {
        $student = User::factory()->create();
        $this->classroom->members()->attach($student->id);
        Sanctum::actingAs($student);

        $this->postJson("/api/classrooms/{$this->classroom->id}/assignments", $this->payload())
            ->assertForbidden()
            ->assertJsonPath('message', AssignmentPolicy::OWNER_ONLY_MESSAGE);

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_publish_unpublish_release_and_withhold(): void
    {
        $assignment = Assignment::factory()->for($this->classroom)->withSolution()->create();
        $base = "/api/assignments/{$assignment->id}";

        $this->postJson("{$base}/publication")->assertOk()->assertJsonPath('data.status', 'published');
        $this->assertNotNull($assignment->fresh()->published_at);

        $this->postJson("{$base}/solution-release")->assertOk()->assertJsonPath('data.solution_released', true);
        $this->deleteJson("{$base}/solution-release")->assertOk()->assertJsonPath('data.solution_released', false);

        $this->deleteJson("{$base}/publication")->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null);
    }

    public function test_cannot_release_a_missing_solution(): void
    {
        $assignment = Assignment::factory()->for($this->classroom)->published()->create();

        $this->postJson("/api/assignments/{$assignment->id}/solution-release")
            ->assertUnprocessable()
            ->assertJsonPath('errors.solution_content.0', ChangeAssignmentState::NO_SOLUTION_MESSAGE);

        $this->assertNull($assignment->fresh()->solution_released_at);
    }

    public function test_update_and_remove_base_and_solution(): void
    {
        $assignment = Assignment::factory()->for($this->classroom)->withBase()->solutionReleased()->create();

        $this->patchJson("/api/assignments/{$assignment->id}", ['title' => 'Nouveau titre', 'type' => 'exam'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Nouveau titre')
            ->assertJsonPath('data.type', 'exam')
            ->assertJsonPath('data.has_base', true);

        $this->patchJson("/api/assignments/{$assignment->id}", ['base_content' => null, 'solution_content' => null])
            ->assertOk()
            ->assertJsonPath('data.has_base', false)
            ->assertJsonPath('data.has_solution', false)
            ->assertJsonPath('data.solution_released', false);

        // Un nouveau corrigé n'est pas libéré d'office.
        $this->patchJson("/api/assignments/{$assignment->id}", ['solution_content' => '{"v":2}'])
            ->assertJsonPath('data.solution_released', false);
    }

    public function test_assignment_count_per_classroom_is_bounded(): void
    {
        config(['assignments.max_per_classroom' => 2]);
        Assignment::factory()->count(2)->for($this->classroom)->create();

        $this->postJson("/api/classrooms/{$this->classroom->id}/assignments", $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.title.0', CreateAssignment::QUOTA_MESSAGE);
    }

    public function test_deleting_an_assignment_deletes_its_image(): void
    {
        $assignment = Assignment::factory()->for($this->classroom)->create();
        $this->postJson("/api/assignments/{$assignment->id}/image", ['image' => UploadedFile::fake()->image('a.png')])->assertOk();
        $path = $assignment->fresh()->image_path;
        Storage::disk('local')->assertExists($path);

        $this->deleteJson("/api/assignments/{$assignment->id}")->assertNoContent();

        $this->assertModelMissing($assignment);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_deleting_a_classroom_deletes_its_assignments_and_images(): void
    {
        $assignment = Assignment::factory()->for($this->classroom)->create();
        $this->postJson("/api/assignments/{$assignment->id}/image", ['image' => UploadedFile::fake()->image('a.png')])->assertOk();
        $path = $assignment->fresh()->image_path;

        $this->deleteJson("/api/classrooms/{$this->classroom->id}")->assertNoContent();

        $this->assertModelMissing($assignment);
        Storage::disk('local')->assertMissing($path);
        $this->assertEmpty(Storage::disk('local')->allFiles('assignments'));
    }

    public function test_list_is_sorted_by_due_date_without_heavy_content(): void
    {
        $later = Assignment::factory()->for($this->classroom)->withSolution()->create(['due_at' => now()->addDays(5)]);
        $sooner = Assignment::factory()->for($this->classroom)->create(['due_at' => now()->addDay()]);
        $none = Assignment::factory()->for($this->classroom)->create(['due_at' => null]);

        $response = $this->getJson("/api/classrooms/{$this->classroom->id}/assignments")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$sooner->id, $later->id, $none->id])
            ->assertJsonPath('data.1.has_solution', true);

        foreach (['instructions', 'base_content', 'solution_content'] as $key) {
            $this->assertArrayNotHasKey($key, $response->json('data.0'));
        }
    }
}
