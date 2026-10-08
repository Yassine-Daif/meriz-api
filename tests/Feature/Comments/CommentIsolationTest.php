<?php

namespace Tests\Feature\Comments;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Comment;
use App\Models\Document;
use App\Models\User;
use App\Policies\CommentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\Sanctum as SanctumAlias;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Preuves du cloisonnement des commentaires : seuls l'élève propriétaire du
 * travail et le prof de son devoir y ont accès.
 */
class CommentIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const SOLUTION_MARKER = 'CORRIGE-COMM-6b4d';

    private User $teacher;

    private User $alice;

    private User $classmate;

    private User $outsider;

    private Assignment $assignment;

    private Document $work;

    private Comment $teacherComment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create(['email' => 'prof.secret@univ-lyon1.fr']);
        $this->alice = User::factory()->create(['email' => 'alice.secret@gmail.com']);
        $this->classmate = User::factory()->create();
        $this->outsider = User::factory()->create();

        $classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $classroom->members()->attach([$this->alice->id, $this->classmate->id]);

        $this->assignment = Assignment::factory()->for($classroom)->published()->withBase()
            ->withSolution('{"corrige":"'.self::SOLUTION_MARKER.'"}')
            ->create();

        $this->work = Document::factory()->for($this->alice)->forAssignment($this->assignment)->create();

        $this->teacherComment = Comment::factory()->for($this->work)->for($this->teacher, 'author')
            ->create(['body' => 'Revois cette cardinalité.']);
    }

    public function test_the_two_participants_see_everything_in_both_directions(): void
    {
        // L'élève répond au prof.
        Sanctum::actingAs($this->alice);
        $this->postJson("/api/documents/{$this->work->id}/comments", ['body' => 'Corrigé, merci.'])
            ->assertCreated();

        foreach ([$this->alice, $this->teacher] as $participant) {
            SanctumAlias::actingAs($participant);

            $this->getJson("/api/documents/{$this->work->id}/comments")
                ->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonPath('data.0.body', 'Revois cette cardinalité.')
                ->assertJsonPath('data.1.body', 'Corrigé, merci.');
        }
    }

    public function test_a_third_party_sees_and_creates_nothing(): void
    {
        foreach ([$this->classmate, $this->outsider, User::factory()->teacher()->create()] as $intruder) {
            Sanctum::actingAs($intruder);

            $list = $this->getJson("/api/documents/{$this->work->id}/comments")->assertNotFound();
            $create = $this->postJson("/api/documents/{$this->work->id}/comments", ['body' => 'Coucou'])
                ->assertNotFound();
            $this->deleteJson("/api/comments/{$this->teacherComment->id}")->assertNotFound();
            $this->postJson("/api/comments/{$this->teacherComment->id}/resolution")->assertNotFound();
            $this->deleteJson("/api/comments/{$this->teacherComment->id}/resolution")->assertNotFound();

            foreach ([$list, $create] as $response) {
                $this->assertStringNotContainsString('Revois cette cardinalité.', $response->getContent());
            }
        }

        $this->assertSame(1, Comment::count());
        $this->assertFalse($this->teacherComment->fresh()->isResolved());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function debugModes(): array
    {
        return ['debug désactivé' => ['0'], 'debug activé' => ['1']];
    }

    #[DataProvider('debugModes')]
    public function test_unreachable_work_and_comments_look_alike(string $debug): void
    {
        config(['app.debug' => (bool) $debug]);
        Sanctum::actingAs($this->outsider);

        $documents = [
            'travail d\'Alice' => $this->work->id,
            'ULID inexistant' => (string) Str::ulid(),
            'id mal formé' => 'pas-un-id',
        ];

        foreach ([['GET', '/api/documents/%s/comments'], ['POST', '/api/documents/%s/comments']] as [$method, $pattern]) {
            $bodies = [];

            foreach ($documents as $label => $id) {
                $response = $this->json($method, sprintf($pattern, $id), ['body' => 'x']);
                $this->assertSame(404, $response->status(), "{$method} {$pattern} {$label}");
                $bodies[$label] = $response->getContent();
            }

            $this->assertCount(1, array_unique($bodies), "{$method} {$pattern} : ".json_encode($bodies));
            $this->assertSame('{"message":"Ressource introuvable."}', reset($bodies));
        }

        // Même chose sur un commentaire précis.
        $comments = [
            'commentaire du prof' => $this->teacherComment->id,
            'ULID inexistant' => (string) Str::ulid(),
            'id mal formé' => 'pas-un-id',
        ];
        $bodies = [];

        foreach ($comments as $label => $id) {
            $response = $this->deleteJson("/api/comments/{$id}");
            $this->assertSame(404, $response->status(), $label);
            $bodies[$label] = $response->getContent();
        }

        $this->assertCount(1, array_unique($bodies));
    }

    public function test_the_author_always_comes_from_the_token(): void
    {
        Sanctum::actingAs($this->alice);

        $id = $this->postJson("/api/documents/{$this->work->id}/comments", [
            'body' => 'Ma réponse',
            'user_id' => $this->teacher->id,
            'author_id' => $this->teacher->id,
            'resolved_at' => now()->toIso8601String(),
            'document_id' => Document::factory()->create()->id,
        ])->assertCreated()
            ->assertJsonPath('data.author.id', $this->alice->id)
            ->assertJsonPath('data.resolved', false)
            ->json('data.id');

        $comment = Comment::findOrFail($id);
        $this->assertSame($this->alice->id, $comment->user_id);
        $this->assertSame($this->work->id, $comment->document_id);
        $this->assertNull($comment->resolved_at);
    }

    public function test_deletion_is_reserved_to_the_author_or_the_teacher(): void
    {
        // L'élève ne supprime pas le commentaire du prof : refus clair, pas 404.
        Sanctum::actingAs($this->alice);
        $this->deleteJson("/api/comments/{$this->teacherComment->id}")
            ->assertForbidden()
            ->assertJsonPath('message', CommentPolicy::DELETE_MESSAGE);
        $this->assertModelExists($this->teacherComment);

        // Mais il supprime le sien.
        $mine = Comment::factory()->for($this->work)->for($this->alice, 'author')->create();
        $this->deleteJson("/api/comments/{$mine->id}")->assertNoContent();

        // Et le prof peut supprimer les deux.
        $another = Comment::factory()->for($this->work)->for($this->alice, 'author')->create();
        Sanctum::actingAs($this->teacher);
        $this->deleteJson("/api/comments/{$another->id}")->assertNoContent();
        $this->deleteJson("/api/comments/{$this->teacherComment->id}")->assertNoContent();

        $this->assertSame(0, Comment::count());
    }

    public function test_commenting_does_not_depend_on_live_tracking(): void
    {
        $this->assertFalse($this->assignment->liveTrackingEnabled());

        Sanctum::actingAs($this->teacher);

        $this->getJson("/api/documents/{$this->work->id}/comments")->assertOk();
        $this->postJson("/api/documents/{$this->work->id}/comments", ['body' => 'Correction à froid'])
            ->assertCreated();
    }

    public function test_the_teacher_can_comment_a_draft_or_unpublished_assignment(): void
    {
        $this->assignment->forceFill(['status' => 'draft', 'published_at' => null])->save();

        Sanctum::actingAs($this->teacher);
        $this->postJson("/api/documents/{$this->work->id}/comments", ['body' => 'Même dépublié'])->assertCreated();

        // L'élève garde l'accès aux commentaires de son propre travail.
        Sanctum::actingAs($this->alice);
        $this->getJson("/api/documents/{$this->work->id}/comments")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_no_solution_grade_or_email_leaks(): void
    {
        Sanctum::actingAs($this->teacher);
        $this->postJson("/api/comments/{$this->teacherComment->id}/resolution")->assertOk();

        foreach ([$this->alice, $this->teacher] as $participant) {
            Sanctum::actingAs($participant);
            $body = $this->getJson("/api/documents/{$this->work->id}/comments")->assertOk()->getContent();

            $this->assertStringNotContainsString(self::SOLUTION_MARKER, $body);
            $this->assertStringNotContainsString('alice.secret@gmail.com', $body);
            $this->assertStringNotContainsString('prof.secret@univ-lyon1.fr', $body);
            $this->assertStringNotContainsString('"email"', $body);
            $this->assertStringNotContainsString('grade', $body);
        }
    }

    public function test_every_route_requires_a_token(): void
    {
        $d = $this->work->id;
        $c = $this->teacherComment->id;

        foreach ([
            ['GET', "/api/documents/{$d}/comments"],
            ['POST', "/api/documents/{$d}/comments"],
            ['DELETE', "/api/comments/{$c}"],
            ['POST', "/api/comments/{$c}/resolution"],
            ['DELETE', "/api/comments/{$c}/resolution"],
        ] as [$method, $uri]) {
            $this->json($method, $uri, ['body' => 'x'])->assertUnauthorized();
        }

        $this->assertSame(1, Comment::count());
        $this->assertFalse($this->teacherComment->fresh()->isResolved());
    }
}
