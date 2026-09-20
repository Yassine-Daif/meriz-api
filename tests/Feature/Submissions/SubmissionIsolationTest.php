<?php

namespace Tests\Feature\Submissions;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Submission;
use App\Models\User;
use App\Policies\SubmissionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Preuves du cloisonnement des rendus : chacun ne voit que le sien, le prof
 * du devoir voit les siens, et un rendu noté est verrouillé.
 */
class SubmissionIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const ALICE_MARKER = 'TRAVAIL-ALICE-4d21-prive';

    private User $teacher;

    private User $alice;

    private User $bob;

    private User $outsider;

    private Assignment $assignment;

    private Submission $aliceSubmission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create(['email' => 'prof.secret@univ-lyon1.fr']);
        $this->alice = User::factory()->create(['email' => 'alice.secret@gmail.com']);
        $this->bob = User::factory()->create(['email' => 'bob.secret@gmail.com']);
        $this->outsider = User::factory()->create();

        $classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $classroom->members()->attach([$this->alice->id, $this->bob->id]);

        $this->assignment = Assignment::factory()->for($classroom)->published()->create();
        $this->aliceSubmission = Submission::factory()
            ->for($this->assignment)
            ->for($this->alice, 'student')
            ->create(['content' => '{"travail":"'.self::ALICE_MARKER.'"}']);
    }

    public function test_student_never_sees_another_students_submission(): void
    {
        Sanctum::actingAs($this->bob);

        $responses = [
            'par son id' => $this->getJson("/api/submissions/{$this->aliceSubmission->id}"),
            'mon rendu' => $this->getJson("/api/assignments/{$this->assignment->id}/submission"),
            'liste du devoir' => $this->getJson("/api/assignments/{$this->assignment->id}/submissions"),
            'vue du devoir' => $this->getJson("/api/assignments/{$this->assignment->id}"),
        ];

        $responses['par son id']->assertNotFound();
        $responses['mon rendu']->assertNotFound();
        $responses['liste du devoir']->assertForbidden()
            ->assertJsonPath('message', SubmissionPolicy::TEACHER_ONLY_MESSAGE);

        foreach ($responses as $label => $response) {
            $this->assertStringNotContainsString(self::ALICE_MARKER, $response->getContent(), $label);
            $this->assertStringNotContainsString('alice.secret@gmail.com', $response->getContent(), $label);
        }
    }

    public function test_my_submission_returns_mine_not_a_classmates(): void
    {
        // Le rendu d'Alice existe déjà et il est plus ancien : la route doit
        // renvoyer celui de Bob, pas le premier rendu du devoir.
        Sanctum::actingAs($this->bob);
        $url = "/api/assignments/{$this->assignment->id}/submission";

        $this->putJson($url, ['content' => '{"travail":"de bob"}'])->assertCreated();

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.student.id', $this->bob->id)
            ->assertJsonPath('data.content', '{"travail":"de bob"}')
            ->assertDontSee(self::ALICE_MARKER, false);
    }

    public function test_student_cannot_grade(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson("/api/submissions/{$this->aliceSubmission->id}/grade", ['grade' => '20/20'])
            ->assertForbidden()
            ->assertJsonPath('message', SubmissionPolicy::TEACHER_ONLY_MESSAGE);
        $this->deleteJson("/api/submissions/{$this->aliceSubmission->id}/grade")->assertForbidden();

        $this->assertNull($this->aliceSubmission->fresh()->grade);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function debugModes(): array
    {
        return ['debug désactivé' => ['0'], 'debug activé' => ['1']];
    }

    #[DataProvider('debugModes')]
    public function test_foreign_submission_looks_like_a_missing_one(string $debug): void
    {
        config(['app.debug' => (bool) $debug]);

        foreach ([$this->bob, $this->outsider] as $viewer) {
            Sanctum::actingAs($viewer);

            $targets = [
                'rendu d\'Alice' => $this->aliceSubmission->id,
                'ULID inexistant' => (string) Str::ulid(),
                'id mal formé' => 'pas-un-id',
            ];

            foreach ([['GET', '/api/submissions/%s'], ['POST', '/api/submissions/%s/grade'], ['DELETE', '/api/submissions/%s/grade']] as [$method, $pattern]) {
                $bodies = [];

                foreach ($targets as $label => $id) {
                    $response = $this->json($method, sprintf($pattern, $id), ['grade' => 'x']);
                    $this->assertSame(404, $response->status(), "{$method} {$pattern} {$label}");
                    $bodies[$label] = $response->getContent();
                }

                $this->assertCount(1, array_unique($bodies), "{$method} {$pattern} : ".json_encode($bodies));
                $this->assertSame('{"message":"Ressource introuvable."}', reset($bodies));
            }
        }
    }

    public function test_cannot_submit_on_an_invisible_assignment(): void
    {
        $draft = Assignment::factory()->for($this->assignment->classroom)->create();
        $foreign = Assignment::factory()->published()->create();

        Sanctum::actingAs($this->bob);

        $this->putJson("/api/assignments/{$draft->id}/submission", ['content' => '{"x":1}'])->assertNotFound();
        $this->putJson("/api/assignments/{$foreign->id}/submission", ['content' => '{"x":1}'])->assertNotFound();
        $this->putJson('/api/assignments/'.Str::ulid().'/submission', ['content' => '{"x":1}'])->assertNotFound();

        $this->assertSame(1, Submission::count());
    }

    public function test_outsider_cannot_submit(): void
    {
        Sanctum::actingAs($this->outsider);

        $this->putJson("/api/assignments/{$this->assignment->id}/submission", ['content' => '{"x":1}'])
            ->assertNotFound();

        $this->assertSame(1, Submission::count());
    }

    public function test_teacher_of_the_classroom_cannot_submit(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->putJson("/api/assignments/{$this->assignment->id}/submission", ['content' => '{"x":1}'])
            ->assertForbidden()
            ->assertJsonPath('message', SubmissionPolicy::STUDENT_ONLY_MESSAGE);

        $this->assertSame(1, Submission::count());
    }

    public function test_author_cannot_be_forced(): void
    {
        Sanctum::actingAs($this->bob);

        $id = $this->putJson("/api/assignments/{$this->assignment->id}/submission", [
            'content' => '{"travail":"de bob"}',
            'user_id' => $this->alice->id,
            'student_id' => $this->alice->id,
            'status' => 'graded',
            'grade' => '20/20',
        ])->assertCreated()->json('data.id');

        $submission = Submission::findOrFail($id);
        $this->assertSame($this->bob->id, $submission->user_id);
        $this->assertNull($submission->grade);
        $this->assertFalse($submission->isGraded());

        // Le rendu d'Alice n'a pas bougé.
        $this->assertSame('{"travail":"'.self::ALICE_MARKER.'"}', $this->aliceSubmission->fresh()->content);
        $this->assertSame(2, Submission::count());
    }

    public function test_second_submission_updates_instead_of_duplicating(): void
    {
        Sanctum::actingAs($this->alice);
        $url = "/api/assignments/{$this->assignment->id}/submission";

        $this->putJson($url, ['content' => '{"v":2}'])->assertOk();
        $this->putJson($url, ['content' => '{"v":3}'])->assertOk();

        $this->assertSame(1, Submission::where('user_id', $this->alice->id)->count());
        $this->assertSame('{"v":3}', $this->aliceSubmission->fresh()->content);
    }

    public function test_graded_submission_is_locked_then_reopened(): void
    {
        Sanctum::actingAs($this->teacher);
        $this->postJson("/api/submissions/{$this->aliceSubmission->id}/grade", ['grade' => '14/20'])->assertOk();

        Sanctum::actingAs($this->alice);
        $url = "/api/assignments/{$this->assignment->id}/submission";

        $this->putJson($url, ['content' => '{"triche":true}'])
            ->assertForbidden()
            ->assertJsonPath('message', SubmissionPolicy::LOCKED_MESSAGE);

        // Même avec des données invalides : l'autorisation passe avant.
        $this->putJson($url, [])->assertForbidden();
        $this->assertSame('{"travail":"'.self::ALICE_MARKER.'"}', $this->aliceSubmission->fresh()->content);

        Sanctum::actingAs($this->teacher);
        $this->deleteJson("/api/submissions/{$this->aliceSubmission->id}/grade")->assertOk();

        Sanctum::actingAs($this->alice);
        $this->putJson($url, ['content' => '{"corrige":true}'])->assertOk();
        $this->assertSame('{"corrige":true}', $this->aliceSubmission->fresh()->content);
    }

    public function test_another_teacher_is_refused_everywhere(): void
    {
        Sanctum::actingAs(User::factory()->teacher()->create());

        $this->getJson("/api/assignments/{$this->assignment->id}/submissions")->assertNotFound();
        $this->getJson("/api/submissions/{$this->aliceSubmission->id}")->assertNotFound();
        $this->postJson("/api/submissions/{$this->aliceSubmission->id}/grade", ['grade' => '0/20'])->assertNotFound();
        $this->deleteJson("/api/submissions/{$this->aliceSubmission->id}/grade")->assertNotFound();

        $this->assertNull($this->aliceSubmission->fresh()->grade);
    }

    public function test_teacher_sees_submissions_without_login_emails(): void
    {
        Sanctum::actingAs($this->teacher);

        $list = $this->getJson("/api/assignments/{$this->assignment->id}/submissions")->assertOk();
        $detail = $this->getJson("/api/submissions/{$this->aliceSubmission->id}")->assertOk();

        foreach ([$list, $detail] as $response) {
            $body = $response->getContent();
            $this->assertStringNotContainsString('alice.secret@gmail.com', $body);
            $this->assertStringNotContainsString('"email"', $body);
        }

        $list->assertJsonPath('data.0.student.id', $this->alice->id);
        $detail->assertSee(self::ALICE_MARKER, false);
    }

    public function test_every_route_requires_a_token(): void
    {
        $a = $this->assignment->id;
        $s = $this->aliceSubmission->id;

        foreach ([
            ['PUT', "/api/assignments/{$a}/submission"],
            ['GET', "/api/assignments/{$a}/submission"],
            ['GET', "/api/assignments/{$a}/submissions"],
            ['GET', "/api/submissions/{$s}"],
            ['POST', "/api/submissions/{$s}/grade"],
            ['DELETE', "/api/submissions/{$s}/grade"],
        ] as [$method, $uri]) {
            $this->json($method, $uri, ['content' => '{"x":1}', 'grade' => 'x'])->assertUnauthorized();
        }

        $this->assertSame(1, Submission::count());
        $this->assertNull($this->aliceSubmission->fresh()->grade);
    }
}
