<?php

namespace Tests\Feature\Classrooms;

use App\Actions\Classrooms\CreateClassroom;
use App\Models\Classroom;
use App\Models\User;
use App\Policies\ClassroomPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClassroomManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
        Sanctum::actingAs($this->teacher);
    }

    public function test_teacher_creates_a_classroom(): void
    {
        $response = $this->postJson('/api/classrooms', ['name' => 'Seconde B'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Seconde B')
            ->assertJsonPath('data.my_role', 'teacher')
            ->assertJsonPath('data.members_count', 0)
            ->assertJsonPath('data.members', []);

        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{8}$/', $response->json('data.join_code'));
        $this->assertSame($this->teacher->id, Classroom::findOrFail($response->json('data.id'))->teacher_id);
    }

    public function test_student_cannot_create_a_classroom(): void
    {
        Sanctum::actingAs(User::factory()->academic()->create());

        $this->postJson('/api/classrooms', ['name' => 'Interdite'])
            ->assertForbidden()
            ->assertJsonPath('message', ClassroomPolicy::TEACHER_ONLY_MESSAGE);

        $this->assertDatabaseCount('classrooms', 0);
    }

    public function test_name_is_validated(): void
    {
        $this->postJson('/api/classrooms', [])->assertUnprocessable()->assertJsonValidationErrors(['name']);
        $this->postJson('/api/classrooms', ['name' => str_repeat('a', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_classroom_count_per_teacher_is_bounded(): void
    {
        config(['classrooms.max_per_teacher' => 2]);
        Classroom::factory()->count(2)->for($this->teacher, 'teacher')->create();

        $this->postJson('/api/classrooms', ['name' => 'Une de trop'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', CreateClassroom::QUOTA_MESSAGE);
    }

    public function test_rename_regenerate_and_delete(): void
    {
        $classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $student = User::factory()->create();
        $classroom->members()->attach($student->id);
        $oldCode = $classroom->join_code;

        $this->patchJson("/api/classrooms/{$classroom->id}", ['name' => 'Renommée'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renommée');

        $newCode = $this->postJson("/api/classrooms/{$classroom->id}/code")->assertOk()->json('data.join_code');
        $this->assertNotSame($oldCode, $newCode);
        $this->assertSame($newCode, $classroom->fresh()->join_code);

        $this->deleteJson("/api/classrooms/{$classroom->id}")->assertNoContent();

        $this->assertModelMissing($classroom);
        $this->assertSame(0, DB::table('classroom_user')->count());
        $this->assertModelExists($student);
    }

    public function test_teacher_view_lists_members_with_join_date(): void
    {
        $classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $student = User::factory()->create(['name' => 'Durand', 'first_name' => 'Léa']);
        $classroom->members()->attach($student->id);

        $this->getJson("/api/classrooms/{$classroom->id}")
            ->assertOk()
            ->assertJsonPath('data.members_count', 1)
            ->assertJsonPath('data.members.0.name', 'Durand')
            ->assertJsonPath('data.members.0.first_name', 'Léa')
            ->assertJsonStructure(['data' => ['members' => [['id', 'name', 'first_name', 'bio', 'contact', 'joined_at']]]]);
    }

    public function test_removed_member_loses_access(): void
    {
        $classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $student = User::factory()->create();
        $classroom->members()->attach($student->id);

        $this->deleteJson("/api/classrooms/{$classroom->id}/members/{$student->id}")->assertNoContent();
        $this->assertFalse($classroom->hasMember($student));

        Sanctum::actingAs($student);
        $this->getJson("/api/classrooms/{$classroom->id}")->assertNotFound();
        $this->getJson('/api/classrooms')->assertJsonPath('data', []);
    }

    public function test_member_can_leave(): void
    {
        $classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $student = User::factory()->create();
        $classroom->members()->attach($student->id);

        Sanctum::actingAs($student);
        $this->deleteJson("/api/classrooms/{$classroom->id}/membership")->assertNoContent();

        $this->assertFalse($classroom->hasMember($student));
        $this->getJson("/api/classrooms/{$classroom->id}")->assertNotFound();
    }

    public function test_owner_cannot_leave_their_classroom(): void
    {
        $classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();

        $this->deleteJson("/api/classrooms/{$classroom->id}/membership")
            ->assertForbidden()
            ->assertJsonPath('message', ClassroomPolicy::OWNER_CANNOT_LEAVE_MESSAGE);

        $this->assertModelExists($classroom);
    }

    public function test_classrooms_disappear_with_their_teacher(): void
    {
        $classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $classroom->members()->attach(User::factory()->create()->id);

        $this->teacher->delete();

        $this->assertModelMissing($classroom);
        $this->assertSame(0, DB::table('classroom_user')->count());
    }
}
