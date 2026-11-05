<?php

namespace Tests\Feature\Groups;

use App\Models\Document;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_copies_are_independent_and_retries_do_not_duplicate(): void
    {
        $user = User::factory()->create();
        $source = Document::factory()->create(['user_id' => $user->id, 'content' => '{"source":true}']);
        $groups = Group::factory()->count(2)->create(['creator_id' => $user->id]);
        $groups->each(fn (Group $group) => $group->members()->syncWithoutDetaching([$user->id]));
        Sanctum::actingAs($user);
        $payload = ['group_ids' => $groups->modelKeys(), 'request_key' => (string) Str::uuid()];
        $path = '/api/documents/'.$source->id.'/group-distribution';
        $first = $this->postJson($path, $payload)->assertOk()->json('data');
        $this->postJson($path, $payload)->assertOk()->assertJsonPath('data', $first);
        $this->postJson($path, [...$payload, 'group_ids' => [$groups[0]->id]])->assertUnprocessable();
        $this->assertNotSame($first[0]['document_id'], $first[1]['document_id']);
        $copy = Document::findOrFail($first[0]['document_id']);
        $this->assertNull($copy->assignment_id);
        $copy->update(['content' => '{"changed":true}']);
        $this->assertSame('{"source":true}', Document::findOrFail($first[1]['document_id'])->content);
        $this->assertSame(3, Document::count());
    }

    public function test_group_access_and_storage_are_checked_before_a_partial_distribution(): void
    {
        $user = User::factory()->create();
        $source = Document::factory()->create(['user_id' => $user->id]);
        $group = Group::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/documents/'.$source->id.'/group-distribution', ['group_ids' => [$group->id], 'request_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(0, $group->documents()->count());
        $group->members()->attach($user);
        config(['documents.max_group_storage_bytes' => 1]);
        $this->postJson('/api/documents/'.$source->id.'/group-distribution', ['group_ids' => [$group->id], 'request_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(0, $group->documents()->count());
    }
}
