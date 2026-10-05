<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\CreateGroup;
use App\Models\Document;
use App\Models\Group;
use App\Models\User;
use App\Policies\GroupPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::factory()->create(['name' => 'Martin', 'first_name' => 'Alice']);
        Sanctum::actingAs($this->creator);
    }

    public function test_anyone_can_create_a_group_and_becomes_its_admin(): void
    {
        $response = $this->postJson('/api/groups', ['name' => 'Projet MCD'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Projet MCD')
            ->assertJsonPath('data.my_role', 'admin')
            ->assertJsonPath('data.members_count', 1)
            ->assertJsonPath('data.documents_count', 0)
            ->assertJsonPath('data.creator.first_name', 'Alice');

        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{8}$/', $response->json('data.join_code'));

        $group = Group::findOrFail($response->json('data.id'));
        $this->assertSame($this->creator->id, $group->creator_id);
        // Le créateur est aussi membre : il apparaît dans la présence.
        $this->assertTrue($group->hasMember($this->creator));
    }

    public function test_a_teacher_account_can_also_create_a_group(): void
    {
        Sanctum::actingAs(User::factory()->teacher()->create());

        $this->postJson('/api/groups', ['name' => 'Groupe de profs'])->assertCreated();
    }

    public function test_name_is_validated(): void
    {
        $this->postJson('/api/groups', [])->assertUnprocessable()->assertJsonValidationErrors(['name']);
        $this->postJson('/api/groups', ['name' => str_repeat('a', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_group_count_per_user_is_bounded(): void
    {
        config(['groups.max_per_user' => 2]);
        Group::factory()->count(2)->for($this->creator, 'creator')->create();

        $this->postJson('/api/groups', ['name' => 'Un de trop'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', CreateGroup::QUOTA_MESSAGE);
    }

    public function test_my_groups_lists_created_and_joined_ones(): void
    {
        $mine = Group::factory()->for($this->creator, 'creator')->create(['name' => 'A - le mien']);
        $joined = Group::factory()->create(['name' => 'B - rejoint']);
        $joined->members()->attach($this->creator->id);
        Group::factory()->create(['name' => 'C - étranger']);

        $this->getJson('/api/groups')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$mine->id, $joined->id])
            ->assertJsonPath('data.0.my_role', 'admin')
            ->assertJsonPath('data.1.my_role', 'member')
            ->assertDontSee('C - étranger');
    }

    public function test_rename_and_regenerate_code(): void
    {
        $group = Group::factory()->for($this->creator, 'creator')->create();
        $oldCode = $group->join_code;

        $this->patchJson("/api/groups/{$group->id}", ['name' => 'Renommé'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renommé');

        $newCode = $this->postJson("/api/groups/{$group->id}/code")->assertOk()->json('data.join_code');

        $this->assertNotSame($oldCode, $newCode);
        $this->assertSame($newCode, $group->fresh()->join_code);
    }

    public function test_removed_member_loses_access_to_shared_documents(): void
    {
        $group = Group::factory()->for($this->creator, 'creator')->create();
        $member = User::factory()->create();
        $group->members()->attach($member->id);
        $shared = Document::factory()->for($this->creator)->forGroup($group)->create();

        Sanctum::actingAs($member);
        $this->getJson("/api/documents/{$shared->id}")->assertOk();

        Sanctum::actingAs($this->creator);
        $this->deleteJson("/api/groups/{$group->id}/members/{$member->id}")->assertNoContent();

        Sanctum::actingAs($member);
        $this->getJson("/api/documents/{$shared->id}")->assertNotFound();
        $this->getJson("/api/groups/{$group->id}")->assertNotFound();
        $this->getJson('/api/groups')->assertJsonPath('data', []);
    }

    public function test_a_member_can_leave_but_the_creator_cannot(): void
    {
        $group = Group::factory()->for($this->creator, 'creator')->create();
        $member = User::factory()->create();
        $group->members()->attach($member->id);

        $this->deleteJson("/api/groups/{$group->id}/membership")
            ->assertForbidden()
            ->assertJsonPath('message', GroupPolicy::CREATOR_CANNOT_LEAVE_MESSAGE);

        Sanctum::actingAs($member);
        $this->deleteJson("/api/groups/{$group->id}/membership")->assertNoContent();

        $this->assertFalse($group->hasMember($member));
        $this->getJson("/api/groups/{$group->id}")->assertNotFound();
    }

    public function test_deleting_the_group_removes_shared_documents_but_keeps_personal_ones(): void
    {
        $group = Group::factory()->for($this->creator, 'creator')->create();
        $member = User::factory()->create();
        $group->members()->attach($member->id);

        $shared = Document::factory()->for($member)->forGroup($group)->create();
        $personal = Document::factory()->for($member)->create();

        $this->deleteJson("/api/groups/{$group->id}")->assertNoContent();

        $this->assertModelMissing($group);
        $this->assertModelMissing($shared);
        $this->assertModelExists($personal);
        $this->assertModelExists($member);
        $this->assertSame(0, DB::table('work_group_user')->count());
    }

    public function test_groups_disappear_with_their_creator(): void
    {
        $group = Group::factory()->for($this->creator, 'creator')->create();
        Document::factory()->for($this->creator)->forGroup($group)->create();

        $this->creator->delete();

        $this->assertModelMissing($group);
        $this->assertSame(0, Document::count());
    }
}
