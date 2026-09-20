<?php

namespace Tests\Feature\Submissions;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubmissionFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Assignment $assignment;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $classroom->members()->attach($this->student->id);

        $this->assignment = Assignment::factory()->for($classroom)->published()
            ->create(['due_at' => now()->addWeek()]);
        $this->url = "/api/assignments/{$this->assignment->id}/submission";

        Sanctum::actingAs($this->student);
    }

    public function test_first_submission_then_update(): void
    {
        $this->putJson($this->url, ['content' => '{"v":1}'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.content', '{"v":1}')
            ->assertJsonPath('data.is_late', false)
            ->assertJsonPath('data.grade', null)
            ->assertJsonPath('data.student.id', $this->student->id);

        $submission = Submission::firstOrFail();
        $first = $submission->submitted_at;

        $this->travel(1)->hour();
        $this->putJson($this->url, ['content' => '{"v":2}'])
            ->assertOk()
            ->assertJsonPath('data.id', $submission->id)
            ->assertJsonPath('data.content', '{"v":2}');

        $fresh = $submission->fresh();
        $this->assertSame('{"v":2}', $fresh->content);
        $this->assertTrue($fresh->submitted_at->greaterThan($first));
        $this->assertSame(1, Submission::count());
    }

    public function test_content_is_stored_and_returned_byte_for_byte(): void
    {
        $content = '{"model":{},"list":[],"zoom":1.0,"t":"é\\u00e9"}';

        $id = $this->putJson($this->url, ['content' => $content])->assertCreated()->json('data.id');

        $this->assertSame($content, DB::table('submissions')->where('id', $id)->value('content'));
        $this->assertSame($content, $this->getJson($this->url)->json('data.content'));
    }

    public function test_reading_before_submitting_is_a_404(): void
    {
        $this->getJson($this->url)->assertNotFound();
    }

    public function test_late_submission_is_flagged(): void
    {
        $this->travelTo($this->assignment->due_at->addDay());

        $this->putJson($this->url, ['content' => '{"v":1}'])
            ->assertCreated()
            ->assertJsonPath('data.is_late', true);
    }

    public function test_an_update_after_the_deadline_becomes_late(): void
    {
        $this->putJson($this->url, ['content' => '{"v":1}'])->assertCreated()->assertJsonPath('data.is_late', false);

        $this->travelTo($this->assignment->due_at->addHour());
        $this->putJson($this->url, ['content' => '{"v":2}'])->assertOk()->assertJsonPath('data.is_late', true);
    }

    public function test_assignment_without_deadline_is_never_late(): void
    {
        $this->assignment->forceFill(['due_at' => null])->save();
        $this->travel(1)->year();

        $this->putJson($this->url, ['content' => '{"v":1}'])
            ->assertCreated()
            ->assertJsonPath('data.is_late', false);
    }

    public function test_content_is_validated(): void
    {
        config(['documents.max_content_bytes' => 50]);

        $this->putJson($this->url, [])->assertUnprocessable()->assertJsonValidationErrors(['content']);
        $this->putJson($this->url, ['content' => 'pas du json'])->assertUnprocessable()->assertJsonValidationErrors(['content']);
        $this->putJson($this->url, ['content' => ['objet' => 1]])->assertUnprocessable()->assertJsonValidationErrors(['content']);
        $this->putJson($this->url, ['content' => '"'.str_repeat('a', 60).'"'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);

        $this->assertSame(0, Submission::count());
    }

    public function test_submissions_disappear_with_their_assignment(): void
    {
        $this->putJson($this->url, ['content' => '{"v":1}'])->assertCreated();

        $this->assignment->delete();

        $this->assertSame(0, Submission::count());
    }

    public function test_submissions_disappear_with_their_student(): void
    {
        $this->putJson($this->url, ['content' => '{"v":1}'])->assertCreated();

        $this->student->delete();

        $this->assertSame(0, Submission::count());
    }
}
