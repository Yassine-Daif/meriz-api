<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonMedium;
use App\Models\Submission;
use App\Models\TeachingResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeachingLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function distribution(array $ids, array $extra = []): array
    {
        return [...['classroom_ids' => $ids, 'update_existing' => false, 'publish' => false, 'apply_settings' => false], ...$extra];
    }

    public function test_assignment_distribution_is_idempotent_and_keeps_class_results_and_dates(): void
    {
        $source = Assignment::factory()->create(['solution_content' => '{"solution":1}']);
        $teacher = $source->classroom->teacher;
        $targetClass = Classroom::factory()->create(['teacher_id' => $teacher->id]);
        Sanctum::actingAs($teacher);
        $resourceId = $this->postJson('/api/teaching-resources', ['source_kind' => 'assignment', 'source_id' => $source->id])
            ->assertCreated()->assertJsonMissingPath('data.payload')->json('data.id');
        $path = '/api/teaching-resources/'.$resourceId.'/distribution';
        $id = $this->postJson($path, $this->distribution([$targetClass->id], ['publish' => true]))->assertOk()->json('data.0.id');
        $this->postJson($path, $this->distribution([$targetClass->id]))->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.updated', false);
        $copy = Assignment::findOrFail($id);
        $this->assertNull($copy->solution_released_at);
        $copy->forceFill(['due_at' => '2027-01-01', 'solution_released_at' => now()])->save();
        $custom = ['views' => ['mcd'], 'export_image' => false, 'export_model' => false];
        $copy->update(['interface_settings' => $custom]);
        $submission = Submission::factory()->graded()->create(['assignment_id' => $copy->id]);
        $source->update(['title' => 'Nouvelle version', 'solution_content' => '{"solution":2}', 'interface_settings' => null]);
        $this->postJson('/api/teaching-resources/'.$resourceId.'/refresh', ['source_kind' => 'assignment', 'source_id' => $source->id])
            ->assertOk()->assertJsonPath('data.version', 2);
        $this->postJson($path, $this->distribution([$targetClass->id], ['update_existing' => true]))->assertOk();
        $copy->refresh();
        $this->assertSame('Nouvelle version', $copy->title);
        $this->assertSame('2027-01-01', $copy->due_at->format('Y-m-d'));
        $this->assertNull($copy->solution_released_at);
        $this->assertSame('16/20', $submission->fresh()->grade);
        $this->assertSame(1, $targetClass->assignments()->count());
        $this->assertSame($custom, $copy->interface_settings);
        $copy->forceFill(['solution_released_at' => now()])->save();
        $this->postJson($path, $this->distribution([$targetClass->id], ['update_existing' => true, 'apply_settings' => true]))
            ->assertOk()->assertJsonPath('data.0.updated', true);
        $this->assertNull($copy->fresh()->interface_settings);
        $this->assertNotNull($copy->fresh()->solution_released_at);
    }

    public function test_captured_lesson_media_survive_source_deletion_and_get_independent_ids(): void
    {
        Storage::fake('local');
        $source = Lesson::factory()->create();
        $medium = LessonMedium::factory()->create(['lesson_id' => $source->id, 'path' => $source->mediaDirectory().'/image.png']);
        Storage::disk('local')->put($medium->path, 'image-content');
        $source->update(['blocks' => json_encode([['type' => 'image', 'media_id' => $medium->id, 'alt' => 'Exemple']])]);
        $target = Classroom::factory()->create(['teacher_id' => $source->classroom->teacher_id]);
        Sanctum::actingAs($source->classroom->teacher);
        $id = $this->postJson('/api/teaching-resources', ['source_kind' => 'lesson', 'source_id' => $source->id])->assertCreated()->json('data.id');
        $source->classroom->delete();
        $copyId = $this->postJson('/api/teaching-resources/'.$id.'/distribution', $this->distribution([$target->id]))->assertOk()->json('data.0.id');
        $copy = Lesson::findOrFail($copyId);
        $newMedium = $copy->media()->firstOrFail();
        $this->assertNotSame($medium->id, $newMedium->id);
        $this->assertSame('image-content', Storage::disk('local')->get($newMedium->path));
        $this->assertSame($newMedium->id, json_decode($copy->blocks, true)[0]['media_id']);
        $this->deleteJson('/api/teaching-resources/'.$id)->assertNoContent();
        Storage::disk('local')->assertExists($newMedium->path);
    }

    public function test_library_and_distribution_are_isolated(): void
    {
        $source = Assignment::factory()->create();
        $other = Classroom::factory()->create();
        Sanctum::actingAs($source->classroom->teacher);
        $id = $this->postJson('/api/teaching-resources', ['source_kind' => 'assignment', 'source_id' => $source->id])->assertCreated()->json('data.id');
        $this->postJson('/api/teaching-resources/'.$id.'/distribution', $this->distribution([$other->id]))->assertUnprocessable();
        Sanctum::actingAs($other->teacher);
        $this->getJson('/api/teaching-resources')->assertJsonCount(0, 'data');
        $this->postJson('/api/teaching-resources/'.$id.'/distribution', $this->distribution([$other->id]))->assertNotFound();
        $this->postJson('/api/teaching-resources', ['source_kind' => 'assignment', 'source_id' => $source->id])->assertNotFound();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/teaching-resources')->assertForbidden();
        $this->assertSame(1, TeachingResource::count());
    }

    public function test_missing_media_roll_back_the_snapshot_and_files(): void
    {
        Storage::fake('local');
        $source = Lesson::factory()->create();
        LessonMedium::factory()->create(['lesson_id' => $source->id]);
        Sanctum::actingAs($source->classroom->teacher);
        $this->postJson('/api/teaching-resources', ['source_kind' => 'lesson', 'source_id' => $source->id])->assertUnprocessable();
        $this->assertSame(0, TeachingResource::count());
        $this->assertSame([], Storage::disk('local')->allFiles('teaching-library'));
    }

    public function test_an_old_non_list_lesson_does_not_cause_a_server_error(): void
    {
        $source = Lesson::factory()->create(['blocks' => '"ancien contenu"']);
        Sanctum::actingAs($source->classroom->teacher);
        $this->postJson('/api/teaching-resources', ['source_kind' => 'lesson', 'source_id' => $source->id])
            ->assertUnprocessable()->assertJsonValidationErrors('source_id');
        $this->assertSame(0, TeachingResource::count());
    }

    public function test_a_later_class_quota_failure_rolls_back_earlier_media_copies(): void
    {
        Storage::fake('local');
        $source = Lesson::factory()->create();
        $medium = LessonMedium::factory()->create(['lesson_id' => $source->id]);
        Storage::disk('local')->put($medium->path, 'image');
        $first = Classroom::factory()->create(['teacher_id' => $source->classroom->teacher_id]);
        $full = Classroom::factory()->create(['teacher_id' => $source->classroom->teacher_id]);
        Lesson::factory()->create(['classroom_id' => $full->id]);
        Sanctum::actingAs($source->classroom->teacher);
        $id = $this->postJson('/api/teaching-resources', ['source_kind' => 'lesson', 'source_id' => $source->id])->assertCreated()->json('data.id');
        config(['lessons.max_per_classroom' => 1]);
        $this->postJson('/api/teaching-resources/'.$id.'/distribution', $this->distribution([$first->id, $full->id]))->assertUnprocessable();
        $this->assertSame(0, $first->lessons()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('lessons/'.$first->id));
        $this->assertDatabaseCount('teaching_publications', 1);
    }
}
