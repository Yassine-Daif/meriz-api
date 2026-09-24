<?php

namespace Tests\Feature\Lessons;

use App\Actions\Lessons\CreateLesson;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use App\Policies\LessonPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Classroom $classroom;

    /** Page de blocs couvrant tous les types demandés. */
    private const BLOCKS = '[{"type":"heading","text":"Les cardinalités"},{"type":"text","text":"Un livre a un auteur."},{"type":"link","url":"https://exemple.fr","label":"Fiche"},{"type":"video","url":"https://www.youtube.com/watch?v=abc"},{"type":"image","media_id":"a"},{"type":"audio","media_id":"b"}]';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->teacher = User::factory()->teacher()->create();
        $this->classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        Sanctum::actingAs($this->teacher);
    }

    public function test_teacher_creates_a_draft_lesson_with_all_block_types(): void
    {
        $this->postJson("/api/classrooms/{$this->classroom->id}/lessons", [
            'title' => 'Le MCD',
            'blocks' => self::BLOCKS,
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Le MCD')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.classroom_id', $this->classroom->id)
            ->assertJsonPath('data.media', [])
            ->assertJsonPath('data.blocks', self::BLOCKS);
    }

    public function test_blocks_are_stored_byte_for_byte(): void
    {
        $blocks = '[{"type":"text","text":"é\\u00e9","meta":{},"zoom":1.0}]';

        $id = $this->postJson("/api/classrooms/{$this->classroom->id}/lessons", [
            'title' => 'Fidèle', 'blocks' => $blocks,
        ])->assertCreated()->json('data.id');

        $this->assertSame($blocks, DB::table('lessons')->where('id', $id)->value('blocks'));
        $this->assertSame($blocks, $this->getJson("/api/lessons/{$id}")->json('data.blocks'));
    }

    public function test_student_cannot_create_a_lesson(): void
    {
        $student = User::factory()->create();
        $this->classroom->members()->attach($student->id);
        Sanctum::actingAs($student);

        $this->postJson("/api/classrooms/{$this->classroom->id}/lessons", ['title' => 'x', 'blocks' => '[]'])
            ->assertForbidden()
            ->assertJsonPath('message', LessonPolicy::OWNER_ONLY_MESSAGE);

        $this->assertDatabaseCount('lessons', 0);
    }

    public function test_update_publish_and_unpublish(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->create();
        $url = "/api/lessons/{$lesson->id}";

        $this->patchJson($url, ['title' => 'Titre revu'])->assertOk()->assertJsonPath('data.title', 'Titre revu');
        $this->patchJson($url, ['blocks' => '[{"type":"text","text":"Nouveau"}]'])
            ->assertOk()
            ->assertJsonPath('data.blocks', '[{"type":"text","text":"Nouveau"}]');

        $this->postJson("{$url}/publication")->assertOk()->assertJsonPath('data.status', 'published');
        $this->assertNotNull($lesson->fresh()->published_at);

        $this->deleteJson("{$url}/publication")->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null);
    }

    public function test_input_is_validated(): void
    {
        $url = "/api/classrooms/{$this->classroom->id}/lessons";
        config(['lessons.max_blocks_bytes' => 100]);

        $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['title', 'blocks']);
        $this->postJson($url, ['title' => str_repeat('a', 201), 'blocks' => '[]'])
            ->assertUnprocessable()->assertJsonValidationErrors(['title']);
        $this->postJson($url, ['title' => 'x', 'blocks' => 'pas du json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['blocks']);
        $this->postJson($url, ['title' => 'x', 'blocks' => [['type' => 'text']]])
            ->assertUnprocessable()->assertJsonValidationErrors(['blocks']);
        $this->postJson($url, ['title' => 'x', 'blocks' => '"'.str_repeat('a', 120).'"'])
            ->assertUnprocessable()->assertJsonValidationErrors(['blocks']);

        $this->assertDatabaseCount('lessons', 0);
    }

    public function test_lesson_count_per_classroom_is_bounded(): void
    {
        config(['lessons.max_per_classroom' => 2]);
        Lesson::factory()->count(2)->for($this->classroom)->create();

        $this->postJson("/api/classrooms/{$this->classroom->id}/lessons", ['title' => 'De trop', 'blocks' => '[]'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.title.0', CreateLesson::QUOTA_MESSAGE);
    }

    public function test_list_is_sorted_and_omits_blocks(): void
    {
        $old = Lesson::factory()->for($this->classroom)->create();
        $this->travel(1)->hour();
        $recent = Lesson::factory()->for($this->classroom)->published()->create();

        $response = $this->getJson("/api/classrooms/{$this->classroom->id}/lessons")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$recent->id, $old->id])
            ->assertJsonPath('data.0.media_count', 0);

        $this->assertArrayNotHasKey('blocks', $response->json('data.0'));
    }

    public function test_deleting_a_lesson_deletes_its_media(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->create();
        $this->post("/api/lessons/{$lesson->id}/media", ['file' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])->assertCreated();
        $path = $lesson->media()->first()->path;

        $this->deleteJson("/api/lessons/{$lesson->id}")->assertNoContent();

        $this->assertModelMissing($lesson);
        $this->assertDatabaseCount('lesson_media', 0);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_deleting_a_classroom_deletes_lessons_and_files(): void
    {
        $lesson = Lesson::factory()->for($this->classroom)->create();
        $this->post("/api/lessons/{$lesson->id}/media", ['file' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])->assertCreated();
        $path = $lesson->media()->first()->path;

        $this->deleteJson("/api/classrooms/{$this->classroom->id}")->assertNoContent();

        $this->assertModelMissing($lesson);
        $this->assertDatabaseCount('lesson_media', 0);
        Storage::disk('local')->assertMissing($path);
        $this->assertEmpty(Storage::disk('local')->allFiles('lessons'));
    }
}
