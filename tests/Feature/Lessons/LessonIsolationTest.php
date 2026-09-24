<?php

namespace Tests\Feature\Lessons;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use App\Policies\LessonPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Preuves du cloisonnement des cours : pas de brouillon pour un élève, rien
 * pour un non-membre, gestion et médias réservés.
 */
class LessonIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const DRAFT_MARKER = 'BROUILLON-c47f-invisible';

    private User $teacher;

    private User $student;

    private User $outsider;

    private Classroom $classroom;

    private Lesson $published;

    private Lesson $draft;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $this->classroom->members()->attach($this->student->id);

        $this->published = Lesson::factory()->for($this->classroom)->published()
            ->create(['title' => 'Cours publié']);

        $this->draft = Lesson::factory()->for($this->classroom)
            ->withBlocks('[{"type":"text","text":"'.self::DRAFT_MARKER.'"}]')
            ->create(['title' => self::DRAFT_MARKER]);
    }

    private function upload(Lesson $lesson): string
    {
        Sanctum::actingAs($this->teacher);

        return $this->post("/api/lessons/{$lesson->id}/media", [
            'file' => UploadedFile::fake()->image('schema.png', 20, 20),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    }

    public function test_student_does_not_see_lessons_of_other_classrooms(): void
    {
        $otherClass = Classroom::factory()->create();
        $foreign = Lesson::factory()->for($otherClass)->published()->create(['title' => 'Cours étranger']);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/lessons')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->published->id])
            ->assertDontSee('Cours étranger');

        $this->getJson("/api/lessons/{$foreign->id}")->assertNotFound();
        $this->getJson("/api/classrooms/{$otherClass->id}/lessons")->assertNotFound();
    }

    public function test_student_never_sees_a_draft(): void
    {
        Sanctum::actingAs($this->student);

        $this->getJson("/api/classrooms/{$this->classroom->id}/lessons")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->published->id])
            ->assertDontSee(self::DRAFT_MARKER);

        $this->getJson('/api/lessons')->assertDontSee(self::DRAFT_MARKER);
        $this->getJson("/api/lessons/{$this->draft->id}")->assertNotFound();
    }

    public function test_non_member_sees_nothing(): void
    {
        Sanctum::actingAs($this->outsider);

        $this->getJson("/api/classrooms/{$this->classroom->id}/lessons")->assertNotFound();
        $this->getJson('/api/lessons')->assertJsonPath('data', []);
        $this->getJson("/api/lessons/{$this->published->id}")->assertNotFound();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function debugModes(): array
    {
        return ['debug désactivé' => ['0'], 'debug activé' => ['1']];
    }

    #[DataProvider('debugModes')]
    public function test_draft_and_foreign_lessons_look_like_missing_ones(string $debug): void
    {
        config(['app.debug' => (bool) $debug]);
        $foreign = Lesson::factory()->published()->create();

        foreach ([$this->student, $this->outsider] as $viewer) {
            Sanctum::actingAs($viewer);

            $targets = [
                'brouillon' => $this->draft->id,
                'autre classe' => $foreign->id,
                'ULID inexistant' => (string) Str::ulid(),
                'id mal formé' => 'pas-un-id',
            ];

            foreach ([
                ['GET', '/api/lessons/%s'],
                ['PATCH', '/api/lessons/%s'],
                ['DELETE', '/api/lessons/%s'],
                ['POST', '/api/lessons/%s/publication'],
                ['POST', '/api/lessons/%s/media'],
                ['GET', '/api/lessons/%s/media/'.Str::ulid()],
            ] as [$method, $pattern]) {
                $bodies = [];

                foreach ($targets as $label => $id) {
                    $response = $this->json($method, sprintf($pattern, $id), ['title' => 'x']);
                    $this->assertSame(404, $response->status(), "{$method} {$pattern} {$label}");
                    $bodies[$label] = $response->getContent();
                }

                $this->assertCount(1, array_unique($bodies), "{$method} {$pattern} : ".json_encode($bodies));
                $this->assertSame('{"message":"Ressource introuvable."}', reset($bodies));
            }
        }
    }

    public function test_member_cannot_manage_even_with_invalid_data(): void
    {
        $mediumId = $this->upload($this->published);

        Sanctum::actingAs($this->student);
        $id = $this->published->id;

        $this->postJson("/api/classrooms/{$this->classroom->id}/lessons", [])->assertForbidden();
        $this->patchJson("/api/lessons/{$id}", ['title' => ''])->assertForbidden();
        $this->patchJson("/api/lessons/{$id}", ['title' => 'Piraté'])
            ->assertForbidden()
            ->assertJsonPath('message', LessonPolicy::OWNER_ONLY_MESSAGE);
        $this->postJson("/api/lessons/{$id}/publication")->assertForbidden();
        $this->deleteJson("/api/lessons/{$id}/publication")->assertForbidden();
        $this->postJson("/api/lessons/{$id}/media", [])->assertForbidden();
        $this->deleteJson("/api/lessons/{$id}/media/{$mediumId}")->assertForbidden();
        $this->deleteJson("/api/lessons/{$id}")->assertForbidden();

        $fresh = $this->published->fresh();
        $this->assertSame('Cours publié', $fresh->title);
        $this->assertTrue($fresh->isPublished());
        $this->assertSame(1, $fresh->media()->count());
    }

    public function test_another_teacher_gets_404_and_changes_nothing(): void
    {
        $mediumId = $this->upload($this->published);

        Sanctum::actingAs(User::factory()->teacher()->create());
        $id = $this->published->id;

        $this->postJson("/api/classrooms/{$this->classroom->id}/lessons", [
            'title' => 'Intrus', 'blocks' => '[]',
        ])->assertNotFound();
        $this->patchJson("/api/lessons/{$id}", ['title' => 'Volé'])->assertNotFound();
        $this->deleteJson("/api/lessons/{$id}/publication")->assertNotFound();
        $this->postJson("/api/lessons/{$id}/media", ['file' => UploadedFile::fake()->image('a.png')])->assertNotFound();
        $this->getJson("/api/lessons/{$id}/media/{$mediumId}")->assertNotFound();
        $this->deleteJson("/api/lessons/{$id}/media/{$mediumId}")->assertNotFound();
        $this->deleteJson("/api/lessons/{$id}")->assertNotFound();

        $fresh = $this->published->fresh();
        $this->assertSame('Cours publié', $fresh->title);
        $this->assertTrue($fresh->isPublished());
        $this->assertSame(1, $fresh->media()->count());
        $this->assertSame(2, Lesson::count());
    }

    public function test_media_are_only_served_to_those_who_can_see_the_lesson(): void
    {
        $publishedMedium = $this->upload($this->published);
        $draftMedium = $this->upload($this->draft);

        // Le prof accède aux deux, même au brouillon.
        $this->get("/api/lessons/{$this->draft->id}/media/{$draftMedium}")->assertOk();

        Sanctum::actingAs($this->outsider);
        $this->get("/api/lessons/{$this->published->id}/media/{$publishedMedium}")->assertNotFound();

        Sanctum::actingAs($this->student);
        $this->get("/api/lessons/{$this->draft->id}/media/{$draftMedium}")->assertNotFound();

        $response = $this->get("/api/lessons/{$this->published->id}/media/{$publishedMedium}")->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));

        // Aucune route de stockage public.
        $this->get('/storage/'.$this->published->media()->first()->path)->assertNotFound();
    }

    public function test_medium_of_another_lesson_is_not_reachable(): void
    {
        $draftMedium = $this->upload($this->draft);

        // Le prof lui-même ne peut pas mélanger les cours.
        $this->get("/api/lessons/{$this->published->id}/media/{$draftMedium}")->assertNotFound();

        Sanctum::actingAs($this->student);
        $this->get("/api/lessons/{$this->published->id}/media/{$draftMedium}")->assertNotFound();
    }

    public function test_client_cannot_force_protected_fields(): void
    {
        $otherClass = Classroom::factory()->for($this->teacher, 'teacher')->create();
        Sanctum::actingAs($this->teacher);

        $id = $this->postJson("/api/classrooms/{$this->classroom->id}/lessons", [
            'title' => 'Nouveau',
            'blocks' => '[]',
            'classroom_id' => $otherClass->id,
            'status' => 'published',
            'published_at' => now()->toIso8601String(),
        ])->assertCreated()->json('data.id');

        $created = Lesson::findOrFail($id);
        $this->assertSame($this->classroom->id, $created->classroom_id);
        $this->assertFalse($created->isPublished());
        $this->assertNull($created->published_at);

        $this->patchJson("/api/lessons/{$id}", [
            'classroom_id' => $otherClass->id,
            'status' => 'published',
        ])->assertOk();

        $fresh = $created->fresh();
        $this->assertSame($this->classroom->id, $fresh->classroom_id);
        $this->assertFalse($fresh->isPublished());
    }

    public function test_every_route_requires_a_token(): void
    {
        $mediumId = $this->upload($this->published);
        $this->app['auth']->forgetGuards();

        $c = $this->classroom->id;
        $l = $this->published->id;

        foreach ([
            ['GET', "/api/classrooms/{$c}/lessons"],
            ['POST', "/api/classrooms/{$c}/lessons"],
            ['GET', '/api/lessons'],
            ['GET', "/api/lessons/{$l}"],
            ['PATCH', "/api/lessons/{$l}"],
            ['DELETE', "/api/lessons/{$l}"],
            ['POST', "/api/lessons/{$l}/publication"],
            ['DELETE', "/api/lessons/{$l}/publication"],
            ['POST', "/api/lessons/{$l}/media"],
            ['GET', "/api/lessons/{$l}/media/{$mediumId}"],
            ['DELETE', "/api/lessons/{$l}/media/{$mediumId}"],
        ] as [$method, $uri]) {
            $this->json($method, $uri)->assertUnauthorized();
        }

        $this->assertSame(2, Lesson::count());
    }
}
