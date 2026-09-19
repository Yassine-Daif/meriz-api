<?php

namespace Tests\Feature\Assignments;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Preuves du cloisonnement des devoirs : pas de brouillon ni de corrigé non
 * libéré pour un élève, rien pour un non-membre, gestion réservée au prof.
 */
class AssignmentIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const SOLUTION_MARKER = 'SOLUTION-7f3a9c-ne-doit-jamais-fuiter';

    private const DRAFT_MARKER = 'BROUILLON-b81e-invisible';

    private User $teacher;

    private User $student;

    private User $outsider;

    private Classroom $classroom;

    private Assignment $published;

    private Assignment $draft;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $this->classroom->members()->attach($this->student->id);

        $this->published = Assignment::factory()->for($this->classroom)->published()
            ->withBase('{"base":"de depart"}')
            ->withSolution('{"corrige":"'.self::SOLUTION_MARKER.'"}')
            ->create(['title' => 'Devoir publié']);

        $this->draft = Assignment::factory()->for($this->classroom)
            ->withSolution('{"corrige":"'.self::SOLUTION_MARKER.'"}')
            ->create(['title' => self::DRAFT_MARKER, 'instructions' => self::DRAFT_MARKER]);
    }

    public function test_student_does_not_see_assignments_of_other_classrooms(): void
    {
        $otherClass = Classroom::factory()->create();
        $foreign = Assignment::factory()->for($otherClass)->published()->create(['title' => 'Devoir étranger']);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/assignments')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->published->id])
            ->assertDontSee('Devoir étranger');

        $this->getJson("/api/assignments/{$foreign->id}")->assertNotFound();
        $this->getJson("/api/classrooms/{$otherClass->id}/assignments")->assertNotFound();
        $this->getJson("/api/assignments?classroom={$otherClass->id}")->assertJsonPath('data', []);
    }

    public function test_non_member_sees_nothing(): void
    {
        Sanctum::actingAs($this->outsider);

        $this->getJson("/api/classrooms/{$this->classroom->id}/assignments")->assertNotFound();
        $this->getJson('/api/assignments')->assertJsonPath('data', []);
        $this->getJson("/api/assignments/{$this->published->id}")->assertNotFound();
        $this->postJson("/api/assignments/{$this->published->id}/copy")->assertNotFound();

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_student_never_sees_a_draft(): void
    {
        Sanctum::actingAs($this->student);

        $this->getJson("/api/classrooms/{$this->classroom->id}/assignments")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->published->id])
            ->assertDontSee(self::DRAFT_MARKER);

        $this->getJson('/api/assignments')->assertDontSee(self::DRAFT_MARKER);
        $this->getJson("/api/assignments/{$this->draft->id}")->assertNotFound();
        $this->postJson("/api/assignments/{$this->draft->id}/copy")->assertNotFound();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function debugModes(): array
    {
        return ['debug désactivé' => ['0'], 'debug activé' => ['1']];
    }

    #[DataProvider('debugModes')]
    public function test_draft_and_foreign_assignments_look_like_missing_ones(string $debug): void
    {
        config(['app.debug' => (bool) $debug]);
        $foreign = Assignment::factory()->published()->create();

        foreach ([$this->student, $this->outsider] as $viewer) {
            Sanctum::actingAs($viewer);

            $targets = [
                'brouillon' => $this->draft->id,
                'autre classe' => $foreign->id,
                'ULID inexistant' => (string) Str::ulid(),
                'id mal formé' => 'pas-un-id',
            ];

            foreach ([
                ['GET', '/api/assignments/%s'],
                ['PATCH', '/api/assignments/%s'],
                ['DELETE', '/api/assignments/%s'],
                ['POST', '/api/assignments/%s/publication'],
                ['POST', '/api/assignments/%s/solution-release'],
                ['GET', '/api/assignments/%s/image'],
                ['POST', '/api/assignments/%s/copy'],
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

    public function test_unreleased_solution_never_reaches_a_student(): void
    {
        Sanctum::actingAs($this->student);

        $responses = [
            'liste classe' => $this->getJson("/api/classrooms/{$this->classroom->id}/assignments"),
            'mes devoirs' => $this->getJson('/api/assignments'),
            'vue' => $this->getJson("/api/assignments/{$this->published->id}"),
            'copie' => $this->postJson("/api/assignments/{$this->published->id}/copy"),
            'vue classe' => $this->getJson("/api/classrooms/{$this->classroom->id}"),
            'liste classes' => $this->getJson('/api/classrooms'),
            'join' => $this->postJson('/api/classrooms/join', ['code' => $this->classroom->join_code]),
        ];

        $documentId = $responses['copie']->assertCreated()->json('data.id');
        $responses['document copié'] = $this->getJson("/api/documents/{$documentId}");
        $responses['liste documents'] = $this->getJson('/api/documents');

        foreach ($responses as $label => $response) {
            $this->assertLessThan(300, $response->status(), $label);
            $this->assertStringNotContainsString(self::SOLUTION_MARKER, $response->getContent(), $label);
        }

        $responses['vue']->assertJsonMissingPath('data.solution_content')
            ->assertJsonMissingPath('data.has_solution')
            ->assertJsonPath('data.solution_released', false)
            ->assertJsonPath('data.base_content', '{"base":"de depart"}');
    }

    public function test_released_solution_becomes_visible_to_students(): void
    {
        Sanctum::actingAs($this->teacher);
        $this->postJson("/api/assignments/{$this->published->id}/solution-release")->assertOk();

        Sanctum::actingAs($this->student);
        $this->getJson("/api/assignments/{$this->published->id}")
            ->assertJsonPath('data.solution_released', true)
            ->assertSee(self::SOLUTION_MARKER, false);

        // Les listes ne portent jamais de contenu, même libéré.
        $this->getJson('/api/assignments')->assertDontSee(self::SOLUTION_MARKER, false);
    }

    public function test_released_solution_of_an_unpublished_assignment_stays_hidden(): void
    {
        $this->draft->forceFill(['solution_released_at' => now()])->save();
        $this->published->forceFill(['solution_released_at' => now()])->save();

        Sanctum::actingAs($this->teacher);
        $this->deleteJson("/api/assignments/{$this->published->id}/publication")->assertOk();

        Sanctum::actingAs($this->student);
        $this->getJson("/api/assignments/{$this->published->id}")->assertNotFound();
        $this->getJson("/api/classrooms/{$this->classroom->id}/assignments")
            ->assertJsonPath('data', [])
            ->assertDontSee(self::SOLUTION_MARKER, false);
    }

    public function test_teacher_sees_everything(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->getJson("/api/classrooms/{$this->classroom->id}/assignments")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/assignments/{$this->draft->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.has_solution', true)
            ->assertSee(self::SOLUTION_MARKER, false);
    }

    public function test_another_teacher_gets_404_and_changes_nothing(): void
    {
        Sanctum::actingAs(User::factory()->teacher()->create());
        $id = $this->published->id;

        $this->postJson("/api/classrooms/{$this->classroom->id}/assignments", [
            'title' => 'Intrus', 'instructions' => 'x', 'type' => 'exercise',
        ])->assertNotFound();
        $this->patchJson("/api/assignments/{$id}", ['title' => 'Volé'])->assertNotFound();
        $this->deleteJson("/api/assignments/{$id}/publication")->assertNotFound();
        $this->postJson("/api/assignments/{$id}/solution-release")->assertNotFound();
        $this->postJson("/api/assignments/{$id}/image", ['image' => UploadedFile::fake()->image('a.png')])->assertNotFound();
        $this->deleteJson("/api/assignments/{$id}/image")->assertNotFound();
        $this->deleteJson("/api/assignments/{$id}")->assertNotFound();

        $fresh = $this->published->fresh();
        $this->assertSame('Devoir publié', $fresh->title);
        $this->assertTrue($fresh->isPublished());
        $this->assertFalse($fresh->solutionReleased());
        $this->assertSame(2, Assignment::count());
    }

    public function test_member_cannot_manage_even_with_invalid_data(): void
    {
        Sanctum::actingAs($this->student);
        $id = $this->published->id;

        $this->postJson("/api/classrooms/{$this->classroom->id}/assignments", [])->assertForbidden();
        $this->patchJson("/api/assignments/{$id}", ['type' => 'invalide'])->assertForbidden();
        $this->patchJson("/api/assignments/{$id}", ['title' => 'Piraté'])->assertForbidden();
        $this->postJson("/api/assignments/{$id}/solution-release")->assertForbidden();
        $this->deleteJson("/api/assignments/{$id}/publication")->assertForbidden();
        $this->postJson("/api/assignments/{$id}/image", [])->assertForbidden();
        $this->deleteJson("/api/assignments/{$id}")->assertForbidden();

        $fresh = $this->published->fresh();
        $this->assertSame('Devoir publié', $fresh->title);
        $this->assertTrue($fresh->isPublished());
        $this->assertFalse($fresh->solutionReleased());
    }

    public function test_image_is_only_served_to_those_who_can_see_the_assignment(): void
    {
        Sanctum::actingAs($this->teacher);
        foreach ([$this->published, $this->draft] as $assignment) {
            $this->postJson("/api/assignments/{$assignment->id}/image", [
                'image' => UploadedFile::fake()->image('schema.png', 20, 20),
            ])->assertOk();
        }
        $this->getJson("/api/assignments/{$this->draft->id}/image")->assertOk();

        Sanctum::actingAs($this->outsider);
        $this->get("/api/assignments/{$this->published->id}/image")->assertNotFound();

        Sanctum::actingAs($this->student);
        $this->get("/api/assignments/{$this->draft->id}/image")->assertNotFound();

        $response = $this->get("/api/assignments/{$this->published->id}/image")->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));

        // Aucune route de stockage public n'existe.
        $path = $this->published->fresh()->image_path;
        $this->get('/storage/'.$path)->assertNotFound();
        $this->get('/api/storage/'.$path)->assertNotFound();
    }

    public function test_client_cannot_force_protected_fields(): void
    {
        $otherClass = Classroom::factory()->for($this->teacher, 'teacher')->create();
        Sanctum::actingAs($this->teacher);

        $id = $this->postJson("/api/classrooms/{$this->classroom->id}/assignments", [
            'title' => 'Nouveau',
            'instructions' => 'Consigne',
            'type' => 'exam',
            'classroom_id' => $otherClass->id,
            'status' => 'published',
            'published_at' => now()->toIso8601String(),
            'solution_released_at' => now()->toIso8601String(),
            'image_path' => '../../.env',
        ])->assertCreated()->json('data.id');

        $created = Assignment::findOrFail($id);
        $this->assertSame($this->classroom->id, $created->classroom_id);
        $this->assertFalse($created->isPublished());
        $this->assertNull($created->published_at);
        $this->assertNull($created->solution_released_at);
        $this->assertNull($created->image_path);

        $this->patchJson("/api/assignments/{$id}", [
            'classroom_id' => $otherClass->id,
            'status' => 'published',
            'image_path' => '../../.env',
        ])->assertOk();

        $fresh = $created->fresh();
        $this->assertSame($this->classroom->id, $fresh->classroom_id);
        $this->assertFalse($fresh->isPublished());
        $this->assertNull($fresh->image_path);
    }

    public function test_every_route_requires_a_token(): void
    {
        $c = $this->classroom->id;
        $a = $this->published->id;

        foreach ([
            ['GET', "/api/classrooms/{$c}/assignments"],
            ['POST', "/api/classrooms/{$c}/assignments"],
            ['GET', '/api/assignments'],
            ['GET', "/api/assignments/{$a}"],
            ['PATCH', "/api/assignments/{$a}"],
            ['DELETE', "/api/assignments/{$a}"],
            ['POST', "/api/assignments/{$a}/publication"],
            ['DELETE', "/api/assignments/{$a}/publication"],
            ['POST', "/api/assignments/{$a}/solution-release"],
            ['DELETE', "/api/assignments/{$a}/solution-release"],
            ['GET', "/api/assignments/{$a}/image"],
            ['POST', "/api/assignments/{$a}/image"],
            ['DELETE', "/api/assignments/{$a}/image"],
            ['POST', "/api/assignments/{$a}/copy"],
        ] as [$method, $uri]) {
            $this->json($method, $uri)->assertUnauthorized();
        }

        $this->assertSame(2, Assignment::count());
    }
}
