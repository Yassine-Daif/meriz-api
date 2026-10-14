<?php

namespace Tests\Feature\Account;

use App\Actions\Auth\AuthenticateUser;
use App\Enums\SubmissionStatus;
use App\Http\Requests\Account\DeleteAccountRequest;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonMedium;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'motdepasse-solide';

    private function student(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Martin',
            'first_name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => self::PASSWORD,
            'bio' => 'Première année',
            'bio_shared' => true,
            'contact' => 'discord: alice',
            'contact_shared' => true,
            'avatar_bg' => '#112233',
            'avatar_fg' => '#ffffff',
        ], $attributes));
    }

    private function deleteAccount(User $user, string $password = self::PASSWORD): TestResponse
    {
        Sanctum::actingAs($user);

        return $this->deleteJson('/api/me', ['password' => $password]);
    }

    public function test_a_wrong_password_deletes_nothing(): void
    {
        $alice = $this->student();
        Document::factory()->for($alice)->create();

        $this->deleteAccount($alice, 'pas-le-bon')
            ->assertStatus(422)
            ->assertJsonPath('errors.password.0', DeleteAccountRequest::WRONG_PASSWORD_MESSAGE);

        $alice->refresh();
        $this->assertSame('alice@example.com', $alice->email);
        $this->assertNull($alice->anonymized_at);
        $this->assertSame(1, $alice->documents()->count());
    }

    public function test_a_missing_password_deletes_nothing(): void
    {
        $alice = $this->student();
        Sanctum::actingAs($alice);

        $this->deleteJson('/api/me')->assertStatus(422)->assertJsonValidationErrors(['password']);

        $this->assertNull($alice->fresh()->anonymized_at);
    }

    public function test_deletion_requires_a_token(): void
    {
        $this->deleteJson('/api/me', ['password' => self::PASSWORD])->assertUnauthorized();
    }

    public function test_identifying_data_and_personal_documents_are_erased(): void
    {
        $alice = $this->student();
        $hash = $alice->password;

        $classroom = Classroom::factory()->create();
        $classroom->members()->attach($alice->id);

        $group = Group::factory()->create();
        $group->members()->attach($alice->id);

        Document::factory()->for($alice)->count(3)->create();

        $this->deleteAccount($alice)->assertNoContent();

        $alice->refresh();

        $this->assertSame(User::DELETED_NAME, $alice->name);
        $this->assertNull($alice->first_name);
        $this->assertSame('supprime-'.$alice->id.'@compte.invalid', $alice->email);
        $this->assertNull($alice->bio);
        $this->assertNull($alice->contact);
        $this->assertFalse($alice->bio_shared);
        $this->assertFalse($alice->contact_shared);
        $this->assertNull($alice->avatar_bg);
        $this->assertNull($alice->avatar_fg);
        $this->assertNull($alice->email_verified_at);
        $this->assertNotSame($hash, $alice->password);
        $this->assertFalse(Hash::check(self::PASSWORD, $alice->password));
        $this->assertNotNull($alice->anonymized_at);

        $this->assertSame(0, Document::where('user_id', $alice->id)->count());
        $this->assertSame(0, $alice->classrooms()->count());
        $this->assertSame(0, $alice->groups()->count());
        $this->assertSame(0, $classroom->members()->count());
        $this->assertSame(0, $group->members()->whereKey($alice->id)->count());
    }

    public function test_a_graded_submission_stays_with_the_teacher_under_a_deleted_account(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->create();

        $alice = $this->student();
        $classroom->members()->attach($alice->id);

        $assignment = Assignment::factory()->for($classroom)->published()->create();
        $submission = Submission::factory()->for($assignment)->for($alice, 'student')
            ->graded('16/20', 'Cardinalités justes.')->create();

        $this->deleteAccount($alice)->assertNoContent();

        $submission->refresh();
        $this->assertSame('16/20', $submission->grade);
        $this->assertSame('Cardinalités justes.', $submission->feedback);
        $this->assertSame(SubmissionStatus::Graded, $submission->status);
        $this->assertSame('{"model":{"rendu":true}}', $submission->content);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($teacher);

        $response = $this->getJson("/api/submissions/{$submission->id}")->assertOk();

        $response->assertJsonPath('data.student.name', User::DELETED_NAME)
            ->assertJsonPath('data.student.first_name', null)
            ->assertJsonPath('data.student.bio', null)
            ->assertJsonPath('data.student.contact', null)
            ->assertJsonPath('data.grade', '16/20');

        $this->assertStringNotContainsString('alice@example.com', $response->getContent());
        $this->assertStringNotContainsString('Alice', $response->getContent());
    }

    public function test_comments_stay_in_their_thread_anonymized(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->create();

        $alice = $this->student();
        $classroom->members()->attach($alice->id);

        $group = Group::factory()->for($teacher, 'creator')->create();
        $group->members()->attach($alice->id);
        $shared = Document::factory()->for($teacher)->forGroup($group)->create();

        Comment::factory()->for($shared)->for($alice, 'author')->create(['body' => 'Je prends la partie 2.']);
        Comment::factory()->for($shared)->for($teacher, 'author')->create(['body' => 'Entendu.']);

        $this->deleteAccount($alice)->assertNoContent();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($teacher);

        $response = $this->getJson("/api/documents/{$shared->id}/comments")->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.body', 'Je prends la partie 2.')
            ->assertJsonPath('data.0.author.name', User::DELETED_NAME)
            ->assertJsonPath('data.1.body', 'Entendu.');

        $this->assertStringNotContainsString('alice@example.com', $response->getContent());
    }

    public function test_a_shared_document_stays_in_a_group_of_someone_else(): void
    {
        $lea = User::factory()->create();
        $group = Group::factory()->for($lea, 'creator')->create();

        $alice = $this->student();
        $group->members()->attach($alice->id);

        $shared = Document::factory()->for($alice)->forGroup($group)
            ->create(['name' => 'Travail commun']);

        $this->deleteAccount($alice)->assertNoContent();

        $this->assertNotNull($shared->fresh());
        $this->assertSame('Travail commun', $shared->fresh()->name);
    }

    public function test_a_group_she_created_goes_away_with_its_shared_documents(): void
    {
        $alice = $this->student();
        $group = Group::factory()->for($alice, 'creator')->create();

        $lea = User::factory()->create();
        $group->members()->attach($lea->id);

        $shared = Document::factory()->for($lea)->forGroup($group)->create();

        $this->deleteAccount($alice)->assertNoContent();

        $this->assertNull(Group::find($group->id));
        $this->assertNull(Document::find($shared->id));
    }

    public function test_a_teacher_takes_her_classes_with_her_and_cleans_her_media(): void
    {
        Storage::fake('local');

        $teacher = User::factory()->teacher()->create(['password' => self::PASSWORD]);
        $classroom = Classroom::factory()->for($teacher, 'teacher')->create();

        $alice = User::factory()->create();
        $classroom->members()->attach($alice->id);

        $assignment = Assignment::factory()->for($classroom)->published()->withBase()->create([
            'image_path' => 'assignments/'.$classroom->id.'/photo.png',
            'image_mime' => 'image/png',
            'image_size' => 10,
        ]);
        Storage::disk('local')->put($assignment->image_path, 'des octets');

        $lesson = Lesson::factory()->for($classroom)->published()->create();
        $medium = LessonMedium::factory()->for($lesson)->create([
            'path' => $lesson->mediaDirectory().'/schema.png',
        ]);
        Storage::disk('local')->put($medium->path, 'des octets');

        // Le travail de l'élève, rattaché au devoir du prof.
        $work = Document::factory()->for($alice)->forAssignment($assignment)->create();
        Submission::factory()->for($assignment)->for($alice, 'student')->create();

        $this->deleteAccount($teacher)->assertNoContent();

        $this->assertNull(Classroom::find($classroom->id));
        $this->assertNull(Assignment::find($assignment->id));
        $this->assertNull(Lesson::find($lesson->id));
        $this->assertSame(0, LessonMedium::count());
        $this->assertSame(0, Submission::count());

        Storage::disk('local')->assertMissing($assignment->image_path);
        Storage::disk('local')->assertMissing($medium->path);
        Storage::disk('local')->assertDirectoryEmpty('assignments');

        // L'élève garde son travail, détaché du devoir disparu.
        $work->refresh();
        $this->assertNull($work->assignment_id);
        $this->assertSame($alice->id, $work->user_id);
    }

    public function test_a_deleted_account_can_no_longer_log_in(): void
    {
        $alice = $this->student();

        $this->deleteAccount($alice)->assertNoContent();
        $this->app['auth']->forgetGuards();

        // Ni avec son ancienne adresse, ni avec celle de la pierre tombale.
        $this->postJson('/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => self::PASSWORD,
        ])->assertStatus(422);

        $this->postJson('/api/auth/login', [
            'email' => 'supprime-'.$alice->id.'@compte.invalid',
            'password' => self::PASSWORD,
        ])->assertStatus(422);
    }

    public function test_an_anonymized_account_is_refused_even_with_working_credentials(): void
    {
        // La seconde barrière, prise seule : un compte éteint dont l'email
        // et le mot de passe fonctionneraient encore reste refusé.
        $alice = $this->student();
        $alice->forceFill(['anonymized_at' => now()])->save();

        $this->postJson('/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => self::PASSWORD,
        ])->assertStatus(422)
            ->assertJsonPath('errors.email.0', AuthenticateUser::FAILED_MESSAGE);
    }

    public function test_deletion_kills_every_token(): void
    {
        $alice = $this->student();
        $token = $alice->createToken('test')->plainTextToken;

        $this->deleteAccount($alice)->assertNoContent();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
        $this->assertSame(0, $alice->tokens()->count());
    }

    public function test_only_her_own_account_goes(): void
    {
        $alice = $this->student();
        $bob = User::factory()->create([
            'email' => 'bob@example.com',
            'password' => self::PASSWORD,
        ]);
        Document::factory()->for($bob)->create();

        // Aucun identifiant n'est accepté : la route ne prend que le jeton.
        Sanctum::actingAs($alice);
        $this->deleteJson('/api/me', [
            'password' => self::PASSWORD,
            'user_id' => $bob->id,
            'id' => $bob->id,
            'email' => $bob->email,
        ])->assertNoContent();

        $bob->refresh();
        $this->assertSame('bob@example.com', $bob->email);
        $this->assertNull($bob->anonymized_at);
        $this->assertSame(1, $bob->documents()->count());

        $this->assertNotNull($alice->fresh()->anonymized_at);
    }
}
