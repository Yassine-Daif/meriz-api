<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    private function demoUsers()
    {
        return User::where('email', 'like', '%@'.DemoDataSeeder::DOMAIN);
    }

    public function test_command_creates_a_teacher_with_populated_classrooms(): void
    {
        $this->artisan('meriz:demo-data')
            ->expectsOutputToContain(DemoDataSeeder::TEACHER_EMAIL)
            ->expectsOutputToContain(DemoDataSeeder::TEACHER_PASSWORD)
            ->assertSuccessful();

        $teacher = User::firstWhere('email', DemoDataSeeder::TEACHER_EMAIL);
        $this->assertTrue($teacher->isTeacher());
        $this->assertTrue($teacher->is_academic);

        $classrooms = Classroom::where('teacher_id', $teacher->id)->withCount('members')->get();
        $this->assertCount(3, $classrooms);

        foreach ($classrooms as $classroom) {
            $this->assertGreaterThanOrEqual(8, $classroom->members_count);
            $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{8}$/', $classroom->join_code);
        }
    }

    public function test_some_students_belong_to_several_classrooms(): void
    {
        $this->artisan('meriz:demo-data')->assertSuccessful();

        $multi = $this->demoUsers()->get()->filter(fn (User $u) => $u->classrooms()->count() > 1);

        $this->assertGreaterThanOrEqual(3, $multi->count());
    }

    public function test_profiles_and_avatar_colors_are_varied(): void
    {
        $this->artisan('meriz:demo-data')->assertSuccessful();

        $students = $this->demoUsers()->where('email', '!=', DemoDataSeeder::TEACHER_EMAIL)->get();

        $this->assertTrue($students->contains(fn (User $u) => $u->sharedBio() !== null));
        $this->assertTrue($students->contains(fn (User $u) => $u->bio !== null && ! $u->bio_shared));
        $this->assertTrue($students->contains(fn (User $u) => $u->bio === null && $u->contact === null));
        $this->assertTrue($students->contains(fn (User $u) => $u->avatar_bg === null));
        $this->assertGreaterThan(3, $students->pluck('avatar_bg')->filter()->unique()->count());

        // Des noms français, pas des identifiants techniques.
        $this->assertTrue($students->every(fn (User $u) => filled($u->name) && filled($u->first_name)));
    }

    public function test_printed_credentials_really_work(): void
    {
        $this->artisan('meriz:demo-data')->assertSuccessful();

        $token = $this->postJson('/api/auth/login', [
            'email' => DemoDataSeeder::TEACHER_EMAIL,
            'password' => DemoDataSeeder::TEACHER_PASSWORD,
        ])->assertOk()->json('data.token');

        $this->withToken($token)->getJson('/api/classrooms?role=teacher')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_running_twice_does_not_duplicate(): void
    {
        $this->artisan('meriz:demo-data')->assertSuccessful();
        $firstCount = $this->demoUsers()->count();

        $this->artisan('meriz:demo-data')->assertSuccessful();

        $this->assertSame($firstCount, $this->demoUsers()->count());
        $this->assertSame(1, User::where('email', DemoDataSeeder::TEACHER_EMAIL)->count());
        $this->assertSame(3, Classroom::count());
    }

    public function test_existing_data_is_never_touched(): void
    {
        $realUser = User::factory()->create(['email' => 'vrai.compte@gmail.com']);
        $realTeacher = User::factory()->teacher()->create(['email' => 'vrai.prof@univ-lyon1.fr']);
        $realClassroom = Classroom::factory()->for($realTeacher, 'teacher')->create();
        $realClassroom->members()->attach($realUser->id);
        $realAssignment = Assignment::factory()->for($realClassroom)->create();
        $realDocument = Document::factory()->for($realUser)->create();

        $this->artisan('meriz:demo-data')->assertSuccessful();
        $this->artisan('meriz:demo-data')->assertSuccessful();

        $this->assertModelExists($realUser);
        $this->assertModelExists($realTeacher);
        $this->assertModelExists($realClassroom);
        $this->assertModelExists($realAssignment);
        $this->assertModelExists($realDocument);
        $this->assertSame(1, $realClassroom->members()->count());
    }

    public function test_purge_removes_its_own_classrooms_and_assignment_images(): void
    {
        Storage::fake('local');
        $this->artisan('meriz:demo-data')->assertSuccessful();

        $teacher = User::firstWhere('email', DemoDataSeeder::TEACHER_EMAIL);
        $classroom = Classroom::where('teacher_id', $teacher->id)->firstOrFail();
        $assignment = Assignment::factory()->for($classroom)->create();
        Storage::disk('local')->put($path = $assignment->imageDirectory().'/image.png', 'x');
        $assignment->forceFill(['image_path' => $path, 'image_mime' => 'image/png', 'image_size' => 1])->save();

        $this->artisan('meriz:demo-data')->assertSuccessful();

        $this->assertModelMissing($assignment);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_command_asks_for_confirmation_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('meriz:demo-data')
            ->expectsConfirmation('Are you sure you want to run this command?', 'no')
            ->assertFailed();

        $this->assertSame(0, $this->demoUsers()->count());
    }
}
