<?php

namespace Tests\Feature\Account;

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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DataExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function export(User $user): array
    {
        Sanctum::actingAs($user);

        $response = $this->get('/api/me/export');
        $response->assertOk();

        $body = $response->streamedContent();
        $this->assertJson($body);

        return json_decode($body, true);
    }

    private function rawExport(User $user): string
    {
        Sanctum::actingAs($user);

        return $this->get('/api/me/export')->streamedContent();
    }

    public function test_the_export_carries_the_data_of_a_student(): void
    {
        $alice = User::factory()->create([
            'name' => 'Martin',
            'first_name' => 'Alice',
            'email' => 'alice@example.com',
            'bio' => 'Première année',
            'bio_shared' => true,
        ]);

        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->create(['name' => 'BUT2 Info']);
        $classroom->members()->attach($alice->id);

        $assignment = Assignment::factory()->for($classroom)->published()->create(['title' => 'MCD ventes']);
        $work = Document::factory()->for($alice)->forAssignment($assignment)
            ->create(['name' => 'Mon MCD', 'content' => '{"model":{"mien":true}}']);

        Submission::factory()->for($assignment)->for($alice, 'student')
            ->graded('16/20', 'Cardinalités justes.')->create();

        $group = Group::factory()->create(['name' => 'Projet SQL']);
        $group->members()->attach($alice->id);

        Comment::factory()->for($work)->for($alice, 'author')
            ->create(['body' => 'Je revois les cardinalités.']);

        $export = $this->export($alice);

        $this->assertSame('alice@example.com', $export['account']['email']);
        $this->assertSame('Première année', $export['account']['bio']);
        $this->assertSame('student', $export['account']['role']);

        $this->assertSame('Mon MCD', $export['documents'][0]['name']);
        $this->assertSame('{"model":{"mien":true}}', $export['documents'][0]['content']);
        $this->assertSame($assignment->id, $export['documents'][0]['assignment_id']);

        $this->assertSame('BUT2 Info', $export['classrooms']['member_of'][0]['name']);
        $this->assertSame([], $export['classrooms']['taught']);
        $this->assertSame('Projet SQL', $export['groups']['member_of'][0]['name']);
        $this->assertSame([], $export['groups']['created']);

        $this->assertSame('16/20', $export['submissions'][0]['grade']);
        $this->assertSame('Cardinalités justes.', $export['submissions'][0]['feedback']);
        $this->assertSame('MCD ventes', $export['submissions'][0]['assignment_title']);
        $this->assertSame('BUT2 Info', $export['submissions'][0]['classroom_name']);
        $this->assertSame('{"model":{"rendu":true}}', $export['submissions'][0]['content']);

        $this->assertSame('Je revois les cardinalités.', $export['comments'][0]['body']);

        // Élève : ni devoirs ni cours, ce n'est pas son espace.
        $this->assertSame([], $export['assignments']);
        $this->assertSame([], $export['lessons']);
    }

    public function test_the_export_carries_the_data_of_a_teacher_with_its_media_described(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->create(['name' => 'BUT1']);
        $classroom->members()->attach(User::factory()->create()->id);

        $assignment = Assignment::factory()->for($classroom)->published()->withBase()->withSolution()
            ->create([
                'title' => 'Exercice 3',
                'image_path' => 'assignments/'.$classroom->id.'/photo.png',
                'image_mime' => 'image/png',
                'image_size' => 2048,
            ]);

        $lesson = Lesson::factory()->for($classroom)->published()
            ->withBlocks('[{"type":"heading","text":"Les cardinalités"}]')
            ->create(['title' => 'Cours 1']);
        $medium = LessonMedium::factory()->for($lesson)->create(['name' => 'schema.png', 'size' => 1024]);

        $export = $this->export($teacher);

        $this->assertSame('BUT1', $export['classrooms']['taught'][0]['name']);
        $this->assertSame(1, $export['classrooms']['taught'][0]['members_count']);

        $exported = $export['assignments'][0];
        $this->assertSame('Exercice 3', $exported['title']);
        $this->assertSame('{"model":{"base":true}}', $exported['base_content']);
        $this->assertSame('{"model":{"solution":true}}', $exported['solution_content']);
        $this->assertSame(2048, $exported['image']['bytes']);
        $this->assertSame('/api/assignments/'.$assignment->id.'/image', $exported['image']['url']);

        $exportedLesson = $export['lessons'][0];
        $this->assertSame('Cours 1', $exportedLesson['title']);
        $this->assertSame('[{"type":"heading","text":"Les cardinalités"}]', $exportedLesson['blocks']);
        $this->assertSame('schema.png', $exportedLesson['media'][0]['name']);
        $this->assertSame(1024, $exportedLesson['media'][0]['bytes']);
        $this->assertSame(
            '/api/lessons/'.$lesson->id.'/media/'.$medium->id,
            $exportedLesson['media'][0]['url'],
        );

        // Le fichier lui-même n'est pas dans le JSON.
        $this->assertArrayNotHasKey('content_base64', $exportedLesson['media'][0]);
    }

    public function test_the_export_carries_nothing_of_another_person(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->create();

        $alice = User::factory()->create(['name' => 'Martin', 'first_name' => 'Alice']);
        $bob = User::factory()->create([
            'name' => 'Durand',
            'first_name' => 'Bob',
            'email' => 'bob-secret@example.com',
            'bio' => 'La presentation de Bob',
            'contact' => 'discord-de-bob',
        ]);

        $classroom->members()->attach([$alice->id, $bob->id]);

        $assignment = Assignment::factory()->for($classroom)->published()->create();
        $bobWork = Document::factory()->for($bob)->forAssignment($assignment)
            ->create(['name' => 'MCD de Bob', 'content' => '{"model":{"secret-de-bob":true}}']);

        Submission::factory()->for($assignment)->for($bob, 'student')->graded('8/20', 'Note de Bob')->create();
        Comment::factory()->for($bobWork)->for($bob, 'author')->create(['body' => 'Remarque de Bob']);

        // Alice travaille aussi, pour que son export ne soit pas vide.
        Document::factory()->for($alice)->forAssignment($assignment)->create(['name' => 'MCD Alice']);

        $raw = $this->rawExport($alice);

        foreach ([
            'bob-secret@example.com',
            'La presentation de Bob',
            'discord-de-bob',
            'MCD de Bob',
            'secret-de-bob',
            'Note de Bob',
            'Remarque de Bob',
            'Durand',
            'Bob',
        ] as $marker) {
            $this->assertStringNotContainsString($marker, $raw, "L'export laisse fuir : {$marker}");
        }

        $this->assertStringContainsString('MCD Alice', $raw);
    }

    public function test_a_student_export_never_carries_a_solution_or_a_teacher_draft(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->create();

        $alice = User::factory()->create();
        $classroom->members()->attach($alice->id);

        Assignment::factory()->for($classroom)->create([
            'title' => 'Brouillon du prof',
            'solution_content' => '{"model":{"le-corrige":true}}',
        ]);

        Document::factory()->for($alice)->create();

        $raw = $this->rawExport($alice);

        $this->assertStringNotContainsString('le-corrige', $raw);
        $this->assertStringNotContainsString('Brouillon du prof', $raw);
    }

    public function test_two_accounts_each_get_their_own_file(): void
    {
        $alice = User::factory()->create(['email' => 'alice@example.com']);
        $bob = User::factory()->create(['email' => 'bob@example.com']);

        $this->assertSame('alice@example.com', $this->export($alice)['account']['email']);

        $this->app['auth']->forgetGuards();

        $this->assertSame('bob@example.com', $this->export($bob)['account']['email']);
    }

    public function test_the_export_is_offered_as_a_download(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->get('/api/me/export');

        $response->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertDownload('meriz-donnees-'.$user->id.'-'.now()->format('Y-m-d').'.json');
    }

    public function test_the_export_requires_a_token(): void
    {
        $this->getJson('/api/me/export')->assertUnauthorized();
    }
}
