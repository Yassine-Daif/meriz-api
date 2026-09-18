<?php

namespace Tests\Feature\Classrooms;

use App\Actions\Classrooms\JoinClassroom;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JoinClassroomTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create();
        $this->classroom = Classroom::factory()->create();
        Sanctum::actingAs($this->student);
    }

    public function test_student_joins_with_the_code(): void
    {
        $this->postJson('/api/classrooms/join', ['code' => $this->classroom->join_code])
            ->assertOk()
            ->assertJsonPath('data.id', $this->classroom->id)
            ->assertJsonPath('data.my_role', 'student')
            ->assertJsonPath('data.members_count', 1);

        $this->assertTrue($this->classroom->hasMember($this->student));
        $this->getJson("/api/classrooms/{$this->classroom->id}")->assertOk();
    }

    public function test_code_is_normalized(): void
    {
        $code = $this->classroom->join_code;
        $messy = ' '.strtolower(substr($code, 0, 4)).'-'.strtolower(substr($code, 4)).' ';

        $this->postJson('/api/classrooms/join', ['code' => $messy])->assertOk();

        $this->assertTrue($this->classroom->hasMember($this->student));
    }

    public function test_joining_twice_is_idempotent(): void
    {
        $this->postJson('/api/classrooms/join', ['code' => $this->classroom->join_code])->assertOk();
        $this->postJson('/api/classrooms/join', ['code' => $this->classroom->join_code])
            ->assertOk()
            ->assertJsonPath('data.members_count', 1);

        $this->assertSame(1, $this->classroom->members()->count());
    }

    public function test_student_can_belong_to_several_classrooms(): void
    {
        $second = Classroom::factory()->create();

        $this->postJson('/api/classrooms/join', ['code' => $this->classroom->join_code])->assertOk();
        $this->postJson('/api/classrooms/join', ['code' => $second->join_code])->assertOk();

        $ids = $this->getJson('/api/classrooms')->json('data.*.id');
        $this->assertEqualsCanonicalizing([$this->classroom->id, $second->id], $ids);
    }

    public function test_wrong_code_fails_cleanly(): void
    {
        foreach (['ZZZZZZZZ', 'x', str_repeat('A', 20), '  '] as $code) {
            $response = $this->postJson('/api/classrooms/join', ['code' => $code])->assertUnprocessable();

            if (trim($code) !== '') {
                $response->assertExactJson([
                    'message' => JoinClassroom::INVALID_CODE_MESSAGE,
                    'errors' => ['code' => [JoinClassroom::INVALID_CODE_MESSAGE]],
                ]);
            }
        }

        $this->postJson('/api/classrooms/join', ['code' => str_repeat('A', 21)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertSame(0, $this->student->classrooms()->count());
    }

    public function test_guessing_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/classrooms/join', ['code' => 'ZZZZZZZ'.$i])->assertUnprocessable();
        }

        // Même le bon code est bloqué une fois la limite atteinte.
        $this->postJson('/api/classrooms/join', ['code' => $this->classroom->join_code])->assertTooManyRequests();
        $this->assertFalse($this->classroom->hasMember($this->student));
    }

    public function test_regenerated_code_invalidates_the_old_one(): void
    {
        $oldCode = $this->classroom->join_code;

        Sanctum::actingAs($this->classroom->teacher);
        $newCode = $this->postJson("/api/classrooms/{$this->classroom->id}/code")
            ->assertOk()
            ->json('data.join_code');

        $this->assertNotSame($oldCode, $newCode);

        Sanctum::actingAs($this->student);
        $this->postJson('/api/classrooms/join', ['code' => $oldCode])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', JoinClassroom::INVALID_CODE_MESSAGE);
        $this->postJson('/api/classrooms/join', ['code' => $newCode])->assertOk();
    }

    public function test_teacher_cannot_join_their_own_classroom(): void
    {
        Sanctum::actingAs($this->classroom->teacher);

        $this->postJson('/api/classrooms/join', ['code' => $this->classroom->join_code])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', JoinClassroom::OWNER_MESSAGE);

        $this->assertSame(0, $this->classroom->members()->count());
    }

    public function test_full_classroom_is_refused(): void
    {
        config(['classrooms.max_members' => 1]);
        $this->classroom->members()->attach(User::factory()->create()->id);

        $this->postJson('/api/classrooms/join', ['code' => $this->classroom->join_code])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', JoinClassroom::FULL_MESSAGE);
    }

    public function test_membership_count_per_user_is_bounded(): void
    {
        config(['classrooms.max_memberships_per_user' => 1]);
        $this->postJson('/api/classrooms/join', ['code' => $this->classroom->join_code])->assertOk();

        $this->postJson('/api/classrooms/join', ['code' => Classroom::factory()->create()->join_code])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', JoinClassroom::TOO_MANY_MESSAGE);
    }
}
