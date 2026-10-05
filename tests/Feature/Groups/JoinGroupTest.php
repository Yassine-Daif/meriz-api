<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\JoinGroup;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JoinGroupTest extends TestCase
{
    use RefreshDatabase;

    private User $newcomer;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->newcomer = User::factory()->create();
        $this->group = Group::factory()->create(['name' => 'Projet MCD']);
        Sanctum::actingAs($this->newcomer);
    }

    public function test_joining_with_the_code(): void
    {
        $this->postJson('/api/groups/join', ['code' => $this->group->join_code])
            ->assertOk()
            ->assertJsonPath('data.id', $this->group->id)
            ->assertJsonPath('data.my_role', 'member')
            ->assertJsonPath('data.members_count', 2)
            ->assertJsonMissingPath('data.join_code');

        $this->assertTrue($this->group->hasMember($this->newcomer));
        $this->getJson("/api/groups/{$this->group->id}")->assertOk();
    }

    public function test_the_code_is_normalized(): void
    {
        $code = $this->group->join_code;
        $messy = ' '.strtolower(substr($code, 0, 4)).'-'.strtolower(substr($code, 4)).' ';

        $this->postJson('/api/groups/join', ['code' => $messy])->assertOk();

        $this->assertTrue($this->group->hasMember($this->newcomer));
    }

    public function test_joining_twice_is_idempotent(): void
    {
        $this->postJson('/api/groups/join', ['code' => $this->group->join_code])->assertOk();
        $this->postJson('/api/groups/join', ['code' => $this->group->join_code])
            ->assertOk()
            ->assertJsonPath('data.members_count', 2);

        $this->assertSame(2, $this->group->members()->count());
    }

    public function test_a_user_can_belong_to_several_groups(): void
    {
        $second = Group::factory()->create();

        $this->postJson('/api/groups/join', ['code' => $this->group->join_code])->assertOk();
        $this->postJson('/api/groups/join', ['code' => $second->join_code])->assertOk();

        $ids = $this->getJson('/api/groups')->json('data.*.id');
        $this->assertEqualsCanonicalizing([$this->group->id, $second->id], $ids);
    }

    public function test_a_wrong_code_fails_cleanly(): void
    {
        foreach (['ZZZZZZZZ', 'x', '  '] as $code) {
            $response = $this->postJson('/api/groups/join', ['code' => $code])->assertUnprocessable();

            if (trim($code) !== '') {
                $response->assertExactJson([
                    'message' => JoinGroup::INVALID_CODE_MESSAGE,
                    'errors' => ['code' => [JoinGroup::INVALID_CODE_MESSAGE]],
                ]);
            }
        }

        $this->postJson('/api/groups/join', ['code' => str_repeat('A', 21)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertSame(0, $this->newcomer->groups()->count());
    }

    public function test_guessing_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/groups/join', ['code' => 'ZZZZZZZ'.$i])->assertUnprocessable();
        }

        // Même le bon code est bloqué une fois la limite atteinte.
        $this->postJson('/api/groups/join', ['code' => $this->group->join_code])->assertTooManyRequests();
        $this->assertFalse($this->group->hasMember($this->newcomer));
    }

    public function test_the_classroom_limit_is_separate(): void
    {
        // La limite des groupes ne consomme pas celle des classes.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/groups/join', ['code' => 'ZZZZZZZ'.$i])->assertUnprocessable();
        }

        $this->postJson('/api/classrooms/join', ['code' => 'YYYYYYYY'])->assertUnprocessable();
    }

    public function test_regenerating_invalidates_the_old_code(): void
    {
        $oldCode = $this->group->join_code;

        Sanctum::actingAs($this->group->creator);
        $newCode = $this->postJson("/api/groups/{$this->group->id}/code")->assertOk()->json('data.join_code');

        Sanctum::actingAs($this->newcomer);
        $this->postJson('/api/groups/join', ['code' => $oldCode])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', JoinGroup::INVALID_CODE_MESSAGE);
        $this->postJson('/api/groups/join', ['code' => $newCode])->assertOk();
    }

    public function test_a_full_group_is_refused(): void
    {
        config(['groups.max_members' => 1]);

        $this->postJson('/api/groups/join', ['code' => $this->group->join_code])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', JoinGroup::FULL_MESSAGE);
    }

    public function test_membership_count_per_user_is_bounded(): void
    {
        config(['groups.max_memberships_per_user' => 1]);
        $this->postJson('/api/groups/join', ['code' => $this->group->join_code])->assertOk();

        $this->postJson('/api/groups/join', ['code' => Group::factory()->create()->join_code])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', JoinGroup::TOO_MANY_MESSAGE);
    }
}
