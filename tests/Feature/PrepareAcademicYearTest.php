<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\User;
use App\Models\WorkspaceCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrepareAcademicYearTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_year_copies_selected_resources_without_old_members_or_grades(): void
    {
        $assignment = Assignment::factory()->published()->create();
        $classroom = $assignment->classroom;
        $teacher = $classroom->teacher;
        $classroom->members()->attach(User::factory()->create());
        Sanctum::actingAs($teacher);
        $source = $this->postJson('/api/collections', ['name' => 'L1', 'kind' => 'classrooms', 'year_start' => 2026, 'archived' => false, 'item_ids' => [$classroom->id]])->assertCreated()->json('data.id');
        $resource = $this->postJson('/api/teaching-resources', ['source_kind' => 'assignment', 'source_id' => $assignment->id])->assertCreated()->json('data.id');
        $payload = ['name' => 'L1', 'year_start' => 2027, 'resource_ids' => [$resource], 'request_key' => (string) Str::uuid()];
        $copy = $this->postJson('/api/collections/'.$source.'/academic-year', $payload)->assertCreated()->json('data');
        $this->postJson('/api/collections/'.$source.'/academic-year', $payload)->assertCreated()->assertJsonPath('data.id', $copy['id']);
        $this->postJson('/api/collections/'.$source.'/academic-year', [...$payload, 'year_start' => 2028])->assertUnprocessable();
        $newClassroom = Classroom::findOrFail($copy['item_ids'][0]);
        $this->assertNotSame($classroom->id, $newClassroom->id);
        $this->assertNotSame($classroom->join_code, $newClassroom->join_code);
        $this->assertSame(0, $newClassroom->members()->count());
        $newAssignment = $newClassroom->assignments()->firstOrFail();
        $this->assertFalse($newAssignment->isPublished());
        $this->assertSame(0, $newAssignment->submissions()->count());
        $this->assertSame(2, WorkspaceCollection::count());
    }
}
