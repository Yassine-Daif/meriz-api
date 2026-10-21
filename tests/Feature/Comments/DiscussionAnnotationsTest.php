<?php

namespace Tests\Feature\Comments;

use App\Models\Comment;
use App\Models\Document;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiscussionAnnotationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_replies_stay_in_their_document_and_reopen_the_discussion(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create();
        $other = Document::factory()->for($user)->create();
        Sanctum::actingAs($user);
        $url = "/api/documents/{$document->id}/comments";
        $parent = $this->postJson($url, ['body' => 'À vérifier', 'position_x' => 12, 'position_y' => 24])
            ->assertCreated()->json('data.id');
        $this->postJson("/api/comments/{$parent}/resolution")->assertOk();
        $reply = $this->postJson($url, ['body' => 'Modifié', 'parent_id' => $parent])
            ->assertCreated()->assertJsonPath('data.parent_id', $parent)->json('data.id');
        $this->assertNull(Comment::findOrFail($parent)->resolved_at);
        $this->postJson("/api/documents/{$other->id}/comments", ['body' => 'Non', 'parent_id' => $parent])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->postJson($url, ['body' => 'Non', 'parent_id' => $reply])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->deleteJson("/api/comments/{$parent}")->assertNoContent();
        $this->assertDatabaseMissing('comments', ['id' => $reply]);
    }

    public function test_saved_drawing_is_bounded_and_does_not_modify_the_model(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create();
        $content = $document->content;
        Sanctum::actingAs($user);
        $url = "/api/documents/{$document->id}/comments";
        $points = [['x' => -15, 'y' => 20], ['x' => 25, 'y' => 35]];
        $id = $this->postJson($url, ['body' => 'Annotation au crayon', 'drawing' => $points])
            ->assertCreated()->assertJsonPath('data.drawing', $points)->assertJsonPath('data.can_delete', true)->json('data.id');
        $this->assertSame($content, $document->fresh()->content);
        foreach ([array_fill(0, 65, $points[0]), [['x' => 1, 'y' => 2]], [['x' => 1, 'y' => 2], ['x' => 1e12, 'y' => 4]]] as $invalid) {
            $this->postJson($url, ['body' => 'Non', 'drawing' => $invalid])->assertUnprocessable();
        }
        $this->postJson($url, ['body' => 'Non', 'drawing' => $points, 'parent_id' => $id])->assertUnprocessable();
    }

    public function test_group_annotations_follow_author_membership_and_moderation_rights(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $owner->id]);
        $group->members()->attach($member->id);
        $document = Document::factory()->for($owner)->create(['group_id' => $group->id]);
        Sanctum::actingAs($owner);
        $url = "/api/documents/{$document->id}/comments";
        $id = $this->postJson($url, ['body' => 'Annotation', 'drawing' => [['x' => 0, 'y' => 0], ['x' => 10, 'y' => 10]]])
            ->assertCreated()->json('data.id');
        Sanctum::actingAs($member);
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.can_delete', false);
        $this->deleteJson("/api/comments/{$id}")->assertForbidden();
        Sanctum::actingAs($outsider);
        $this->getJson($url)->assertNotFound();
        $this->postJson($url, ['body' => 'Non'])->assertNotFound();
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/comments/{$id}")->assertNoContent();
    }
}
