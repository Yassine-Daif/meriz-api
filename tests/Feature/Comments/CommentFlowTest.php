<?php

namespace Tests\Feature\Comments;

use App\Actions\Comments\CreateComment;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Group;
use App\Models\User;
use App\Policies\CommentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $alice;

    private Document $work;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create(['name' => 'Bernard', 'first_name' => 'Claire']);
        $this->alice = User::factory()->create(['name' => 'Martin', 'first_name' => 'Alice']);

        $classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $classroom->members()->attach($this->alice->id);

        $assignment = Assignment::factory()->for($classroom)->published()->withBase()->create();
        $this->work = Document::factory()->for($this->alice)->forAssignment($assignment)->create();
    }

    private function url(): string
    {
        return "/api/documents/{$this->work->id}/comments";
    }

    public function test_a_general_comment_has_no_position(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson($this->url(), ['body' => 'Bon travail dans l\'ensemble.'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Bon travail dans l\'ensemble.')
            ->assertJsonPath('data.position', null)
            ->assertJsonPath('data.resolved', false)
            ->assertJsonPath('data.author.first_name', 'Claire')
            ->assertJsonPath('data.author.role', null);
    }

    public function test_a_placed_bubble_keeps_its_coordinates(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson($this->url(), [
            'body' => 'Cette entité devrait être une association.',
            'position_x' => 412.75,
            'position_y' => -88.5,
        ])->assertCreated()
            ->assertJsonPath('data.position.x', 412.75)
            ->assertJsonPath('data.position.y', -88.5);

        $comment = Comment::firstOrFail();
        $this->assertSame(412.75, $comment->position_x);
        $this->assertSame(-88.5, $comment->position_y);
    }

    public function test_comments_come_back_oldest_first(): void
    {
        Sanctum::actingAs($this->teacher);
        $this->postJson($this->url(), ['body' => 'Premier'])->assertCreated();

        $this->travel(1)->minute();
        Sanctum::actingAs($this->alice);
        $this->postJson($this->url(), ['body' => 'Deuxième'])->assertCreated();

        $this->travel(1)->minute();
        Sanctum::actingAs($this->teacher);
        $this->postJson($this->url(), ['body' => 'Troisième'])->assertCreated();

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.*.body', ['Premier', 'Deuxième', 'Troisième'])
            ->assertJsonPath('data.1.author.first_name', 'Alice');
    }

    public function test_resolving_and_reopening_by_either_participant(): void
    {
        $comment = Comment::factory()->for($this->work)->for($this->teacher, 'author')->create();

        // L'élève classe le point traité.
        Sanctum::actingAs($this->alice);
        $this->postJson("/api/comments/{$comment->id}/resolution")
            ->assertOk()
            ->assertJsonPath('data.resolved', true)
            ->assertJsonPath('data.resolver.first_name', 'Alice');

        $this->assertNotNull($comment->fresh()->resolved_at);

        // Le prof rouvre.
        Sanctum::actingAs($this->teacher);
        $this->deleteJson("/api/comments/{$comment->id}/resolution")
            ->assertOk()
            ->assertJsonPath('data.resolved', false)
            ->assertJsonPath('data.resolved_at', null)
            ->assertJsonMissingPath('data.resolver.id');

        $fresh = $comment->fresh();
        $this->assertNull($fresh->resolved_at);
        $this->assertNull($fresh->resolved_by);
    }

    public function test_input_is_validated(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson($this->url(), [])->assertUnprocessable()->assertJsonValidationErrors(['body']);
        $this->postJson($this->url(), ['body' => str_repeat('a', 2001)])
            ->assertUnprocessable()->assertJsonValidationErrors(['body']);

        // Une seule coordonnée : une bulle a les deux.
        $this->postJson($this->url(), ['body' => 'x', 'position_x' => 10])
            ->assertUnprocessable()->assertJsonValidationErrors(['position_y']);
        $this->postJson($this->url(), ['body' => 'x', 'position_y' => 10])
            ->assertUnprocessable()->assertJsonValidationErrors(['position_x']);

        $this->postJson($this->url(), ['body' => 'x', 'position_x' => 'ici', 'position_y' => 'là'])
            ->assertUnprocessable()->assertJsonValidationErrors(['position_x', 'position_y']);

        $this->postJson($this->url(), ['body' => 'x', 'position_x' => 2_000_000, 'position_y' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors(['position_x']);

        $this->assertSame(0, Comment::count());
    }

    public function test_comment_count_per_document_is_bounded(): void
    {
        config(['comments.max_per_document' => 2]);
        Comment::factory()->count(2)->for($this->work)->for($this->teacher, 'author')->create();

        Sanctum::actingAs($this->teacher);
        $this->postJson($this->url(), ['body' => 'Un de trop'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.body.0', CreateComment::QUOTA_MESSAGE);
    }

    public function test_comments_disappear_with_their_document(): void
    {
        Comment::factory()->count(2)->for($this->work)->for($this->teacher, 'author')->create();

        $this->work->delete();

        $this->assertSame(0, Comment::count());
    }

    public function test_group_members_comment_each_other_and_a_former_member_does_not(): void
    {
        $creator = User::factory()->create();
        $group = Group::factory()->for($creator, 'creator')->create();
        $group->members()->attach($this->alice->id);
        $shared = Document::factory()->for($creator)->forGroup($group)->create();
        $url = "/api/documents/{$shared->id}/comments";

        // Deux membres se répondent.
        Sanctum::actingAs($this->alice);
        $aliceComment = $this->postJson($url, ['body' => 'Je prends la partie clients.'])
            ->assertCreated()->json('data.id');

        Sanctum::actingAs($creator);
        $this->postJson($url, ['body' => 'Parfait, je fais les commandes.'])->assertCreated();
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'data');

        // Le créateur du groupe peut supprimer le commentaire d'un membre.
        $this->deleteJson("/api/comments/{$aliceComment}")->assertNoContent();

        // Alice quitte le groupe : plus rien.
        $group->members()->detach($this->alice->id);
        Sanctum::actingAs($this->alice);
        $this->getJson($url)->assertNotFound();
        $this->postJson($url, ['body' => 'Encore moi'])->assertNotFound();
    }

    public function test_a_member_cannot_delete_another_members_comment(): void
    {
        $creator = User::factory()->create();
        $group = Group::factory()->for($creator, 'creator')->create();
        $group->members()->attach([$this->alice->id, $this->teacher->id]);
        $shared = Document::factory()->for($creator)->forGroup($group)->create();

        $comment = Comment::factory()->for($shared)->for($this->alice, 'author')->create();

        Sanctum::actingAs($this->teacher);
        $this->deleteJson("/api/comments/{$comment->id}")
            ->assertForbidden()
            ->assertJsonPath('message', CommentPolicy::DELETE_MESSAGE);

        $this->assertModelExists($comment);
    }
}
