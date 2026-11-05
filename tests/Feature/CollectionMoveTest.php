<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Group;
use App\Models\User;
use App\Models\WorkspaceCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectionMoveTest extends TestCase
{
    use RefreshDatabase;

    private function collection(User $user, string $kind = 'classrooms'): WorkspaceCollection
    {
        return WorkspaceCollection::forceCreate(['user_id' => $user->id, 'name' => 'L1', 'kind' => $kind, 'year_start' => 2026, 'archived' => false]);
    }

    public function test_move_preserves_other_memberships_class_and_people_and_can_be_undone(): void
    {
        $user = User::factory()->teacher()->create();
        $class = Classroom::factory()->create(['teacher_id' => $user->id]);
        $student = User::factory()->create();
        $class->members()->attach($student);
        [$from, $to, $other] = [$this->collection($user), $this->collection($user), $this->collection($user)];
        $from->classrooms()->attach($class);
        $other->classrooms()->attach($class);
        Sanctum::actingAs($user);
        $payload = ['kind' => 'classrooms', 'item_id' => $class->id, 'from_id' => $from->id, 'to_id' => $to->id];
        $this->postJson('/api/collections/move', $payload)->assertNoContent();
        $this->postJson('/api/collections/move', $payload)->assertNoContent();
        $this->assertFalse($from->classrooms()->exists());
        $this->assertTrue($to->classrooms()->whereKey($class)->exists());
        $this->assertTrue($other->classrooms()->whereKey($class)->exists());
        $this->assertTrue($class->members()->whereKey($student)->exists());
        $this->postJson('/api/collections/move', [...$payload, 'from_id' => $to->id, 'to_id' => $from->id])->assertNoContent();
        $this->assertTrue($from->classrooms()->whereKey($class)->exists());
        $this->assertFalse($to->classrooms()->exists());
    }

    public function test_other_users_destinations_and_classes_are_not_accessible(): void
    {
        $user = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();
        $class = Classroom::factory()->create(['teacher_id' => $user->id]);
        $source = $this->collection($user);
        $source->classrooms()->attach($class);
        Sanctum::actingAs($user);
        $this->postJson('/api/collections/move', ['kind' => 'classrooms', 'item_id' => $class->id, 'from_id' => $source->id, 'to_id' => $this->collection($other)->id])->assertNotFound();
        $this->assertTrue($source->classrooms()->whereKey($class)->exists());
        $this->postJson('/api/collections/move', ['kind' => 'classrooms', 'item_id' => Classroom::factory()->create(['teacher_id' => $other->id])->id, 'from_id' => null, 'to_id' => $source->id])->assertNotFound();
    }

    public function test_unassigned_group_can_move_without_changing_group_access(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();
        $group->members()->attach($user);
        $target = $this->collection($user, 'groups');
        Sanctum::actingAs($user);
        $payload = ['kind' => 'groups', 'item_id' => $group->id, 'from_id' => null, 'to_id' => $target->id];
        $this->postJson('/api/collections/move', $payload)->assertNoContent();
        $this->assertTrue($target->groups()->whereKey($group)->exists());
        $this->assertTrue($group->members()->whereKey($user)->exists());
        $this->postJson('/api/collections/move', [...$payload, 'kind' => 'classrooms'])->assertForbidden();
        $group->members()->detach($user);
        $this->postJson('/api/collections/move', [...$payload, 'from_id' => $target->id, 'to_id' => null])->assertNotFound();
    }

    public function test_stale_source_is_rejected_without_adding_a_second_destination(): void
    {
        $user = User::factory()->teacher()->create();
        $class = Classroom::factory()->create(['teacher_id' => $user->id]);
        Sanctum::actingAs($user);
        $from = $this->collection($user);
        $to = $this->collection($user);
        $this->postJson('/api/collections/move', ['kind' => 'classrooms', 'item_id' => $class->id, 'from_id' => $from->id, 'to_id' => $to->id])->assertUnprocessable();
        $this->assertFalse($to->classrooms()->exists());
    }

    public function test_period_is_compatible_with_existing_clients_and_calendar_year_is_preserved(): void
    {
        $user = User::factory()->teacher()->create();
        Sanctum::actingAs($user);
        $payload = ['kind' => 'classrooms', 'name' => 'L1', 'year_start' => 2026, 'archived' => false, 'item_ids' => []];
        $this->postJson('/api/collections', $payload)->assertCreated()->assertJsonPath('data.period_kind', 'academic')->assertJsonPath('data.archived', false);
        $id = $this->postJson('/api/collections', [...$payload, 'period_kind' => 'calendar'])->assertCreated()->assertJsonPath('data.period_kind', 'calendar')->json('data.id');
        $this->patchJson('/api/collections/'.$id, $payload)->assertOk()->assertJsonPath('data.period_kind', 'calendar');
        $this->patchJson('/api/collections/'.$id, [...$payload, 'period_kind' => 'invalid'])->assertUnprocessable();
    }

    public function test_removal_and_undo_keep_a_second_collection(): void
    {
        $user = User::factory()->teacher()->create();
        $class = Classroom::factory()->create(['teacher_id' => $user->id]);
        [$first, $second] = [$this->collection($user), $this->collection($user)];
        $first->classrooms()->attach($class);
        $second->classrooms()->attach($class);
        Sanctum::actingAs($user);
        $payload = ['kind' => 'classrooms', 'item_id' => $class->id, 'from_id' => $first->id, 'to_id' => null];
        $this->postJson('/api/collections/move', $payload)->assertNoContent();
        $this->postJson('/api/collections/move', $payload)->assertNoContent();
        $this->postJson('/api/collections/move', [...$payload, 'from_id' => null, 'to_id' => $first->id])->assertNoContent();
        $this->assertTrue($first->classrooms()->whereKey($class)->exists());
        $this->assertTrue($second->classrooms()->whereKey($class)->exists());
    }

    public function test_full_destination_does_not_remove_the_source(): void
    {
        $user = User::factory()->teacher()->create();
        [$from, $to] = [$this->collection($user), $this->collection($user)];
        $classes = Classroom::factory()->count(201)->create(['teacher_id' => $user->id]);
        $to->classrooms()->attach($classes->take(200)->modelKeys());
        $class = $classes->last();
        $from->classrooms()->attach($class);
        Sanctum::actingAs($user);
        $this->postJson('/api/collections/move', ['kind' => 'classrooms', 'item_id' => $class->id, 'from_id' => $from->id, 'to_id' => $to->id])->assertUnprocessable();
        $this->assertTrue($from->classrooms()->whereKey($class)->exists());
        $this->assertSame(200, $to->classrooms()->count());
    }

    public function test_metadata_edit_does_not_overwrite_a_move_from_another_window(): void
    {
        $user = User::factory()->teacher()->create();
        $class = Classroom::factory()->create(['teacher_id' => $user->id]);
        [$from, $to] = [$this->collection($user), $this->collection($user)];
        $from->classrooms()->attach($class);
        Sanctum::actingAs($user);
        $this->postJson('/api/collections/move', ['kind' => 'classrooms', 'item_id' => $class->id, 'from_id' => $from->id, 'to_id' => $to->id])->assertNoContent();
        $this->patchJson('/api/collections/'.$from->id.'/settings', ['name' => 'Nouveau nom', 'period_kind' => 'calendar'])->assertOk();
        $this->assertFalse($from->classrooms()->exists());
        $this->assertTrue($to->classrooms()->whereKey($class)->exists());
        $this->patchJson('/api/collections/'.$from->id.'/settings', ['item_ids' => [$class->id]])->assertUnprocessable();
        $this->patchJson('/api/collections/'.$this->collection(User::factory()->teacher()->create())->id.'/settings', ['archived' => true])->assertNotFound();
    }
}
