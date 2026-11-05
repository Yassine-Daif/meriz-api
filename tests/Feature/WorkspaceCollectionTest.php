<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Group;
use App\Models\User;
use App\Models\WorkspaceCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceCollectionTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'L1', 'kind' => 'classrooms', 'year_start' => 2026, 'archived' => false, 'item_ids' => []], $overrides);
    }

    public function test_teacher_organizes_classes_and_preserves_them_on_removal(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create(['teacher_id' => $teacher->id]);
        Sanctum::actingAs($teacher);
        $id = $this->postJson('/api/collections', $this->payload(['item_ids' => [$classroom->id]]))
            ->assertCreated()->assertJsonPath('data.year_start', 2026)->json('data.id');
        $this->patchJson('/api/collections/'.$id, $this->payload(['archived' => true, 'item_ids' => [$classroom->id]]))
            ->assertOk()->assertJsonPath('data.archived', true);
        $this->deleteJson('/api/collections/'.$id)->assertNoContent();
        $this->assertModelExists($classroom);
    }

    public function test_collection_and_class_ownership_cannot_be_forged(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherClass = Classroom::factory()->create();
        Sanctum::actingAs($teacher);
        $this->postJson('/api/collections', $this->payload(['item_ids' => [$otherClass->id]]))
            ->assertUnprocessable()->assertJsonValidationErrors('item_ids');
        $id = $this->postJson('/api/collections', $this->payload())->assertCreated()->json('data.id');
        Sanctum::actingAs($otherClass->teacher);
        $this->getJson('/api/collections')->assertJsonCount(0, 'data');
        $this->patchJson('/api/collections/'.$id, $this->payload())->assertNotFound();
        $this->deleteJson('/api/collections/'.$id)->assertNotFound();
    }

    public function test_group_collections_are_private_and_do_not_keep_lost_memberships(): void
    {
        $student = User::factory()->create();
        $group = Group::factory()->create();
        $group->members()->attach($student);
        Sanctum::actingAs($student);
        $id = $this->postJson('/api/collections', $this->payload(['kind' => 'groups', 'item_ids' => [$group->id]]))
            ->assertCreated()->json('data.id');
        $group->members()->detach($student);
        $this->getJson('/api/collections')->assertJsonPath('data.0.item_ids', []);
        $this->assertDatabaseHas('workspace_collections', ['id' => $id, 'user_id' => $student->id]);
        $this->postJson('/api/collections', $this->payload())->assertForbidden();
        $this->assertSame(1, WorkspaceCollection::count());
    }
}
