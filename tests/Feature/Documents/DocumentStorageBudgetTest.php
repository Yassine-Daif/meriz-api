<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentStorageBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_is_counted_in_bytes_and_growth_is_bounded(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config(['documents.max_user_storage_bytes' => 20]);
        $id = $this->postJson('/api/documents', ['name' => 'Unicode', 'content' => '{"é":"é"}'])
            ->assertCreated()->json('data.id');
        $document = Document::findOrFail($id);
        $this->assertSame(strlen('{"é":"é"}'), $document->content_bytes);
        $this->postJson('/api/documents', ['name' => 'Trop', 'content' => '{"abc":"abcdefgh"}'])->assertUnprocessable();
        $this->patchJson('/api/documents/'.$id, ['content' => '{"long":"abcdefghijklmnopqrstuvwxyz"}'])->assertUnprocessable();
        $this->assertSame('{"é":"é"}', $document->fresh()->content);
        config(['documents.max_user_storage_bytes' => 1]);
        $this->patchJson('/api/documents/'.$id, ['content' => '{}'])->assertOk();
        $this->patchJson('/api/documents/'.$id, ['name' => 'Réduit'])->assertOk();
    }

    public function test_group_storage_is_shared_without_consuming_personal_budget(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['creator_id' => $user->id]);
        $group->members()->syncWithoutDetaching([$user->id]);
        Sanctum::actingAs($user);
        config(['documents.max_user_storage_bytes' => 2, 'documents.max_group_storage_bytes' => 2]);
        $this->postJson('/api/groups/'.$group->id.'/documents', ['name' => 'Groupe', 'content' => '{}'])->assertCreated();
        $this->postJson('/api/documents', ['name' => 'Personnel', 'content' => '{}'])->assertCreated();
        $this->postJson('/api/groups/'.$group->id.'/documents', ['name' => 'Trop', 'content' => '{}'])->assertUnprocessable();
    }
}
