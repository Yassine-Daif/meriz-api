<?php

namespace Tests\Feature\Live;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Document;
use App\Models\User;
use App\Policies\AssignmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Le ping « j'observe toujours » : il garde juste l'heure de dernière lecture
 * pendant que le prof regarde par websocket, sans rien écrire d'autre.
 */
class ObservedPingTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $alice;

    private User $outsider;

    private Classroom $classroom;

    private Assignment $assignment;

    private Document $work;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
        $this->alice = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $this->classroom->members()->attach($this->alice->id);

        $this->assignment = Assignment::factory()->for($this->classroom)->published()->withBase()->create();
        $this->work = Document::factory()->for($this->alice)->forAssignment($this->assignment)
            ->create(['content' => '{"travail":1}']);

        $this->url = "/api/assignments/{$this->assignment->id}/live/{$this->alice->id}/seen";
    }

    private function enableTracking(): void
    {
        $this->assignment->forceFill(['live_tracking' => true, 'live_tracking_enabled_at' => now()])->save();
    }

    public function test_ping_refreshes_the_trace_the_student_sees(): void
    {
        $this->enableTracking();
        Sanctum::actingAs($this->teacher);

        $first = $this->postJson($this->url)->assertOk()->json('data.observed_at');
        $this->assertNotNull($first);

        $this->travel(1)->minute();
        $second = $this->postJson($this->url)->assertOk()->json('data.observed_at');
        $this->assertNotSame($first, $second);

        // L'élève voit la dernière heure.
        Sanctum::actingAs($this->alice);
        $this->getJson("/api/documents/{$this->work->id}")
            ->assertOk()
            ->assertJsonPath('data.last_observed_at', $second);
    }

    public function test_ping_writes_nothing_else(): void
    {
        $this->enableTracking();
        $before = $this->work->fresh();
        Sanctum::actingAs($this->teacher);

        $this->travel(1)->minute();
        $this->postJson($this->url)->assertOk();

        $after = $this->work->fresh();
        $this->assertSame($before->content, $after->content);
        $this->assertSame($before->name, $after->name);
        $this->assertTrue($before->updated_at->equalTo($after->updated_at));
    }

    public function test_ping_is_refused_while_tracking_is_off(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson($this->url)
            ->assertForbidden()
            ->assertJsonPath('message', AssignmentPolicy::TRACKING_OFF_MESSAGE);

        $this->assertNull($this->work->fresh()->last_observed_at);
    }

    public function test_ping_is_refused_for_students_and_other_teachers(): void
    {
        $this->enableTracking();

        Sanctum::actingAs($this->alice);
        $this->postJson($this->url)->assertForbidden();

        Sanctum::actingAs(User::factory()->teacher()->create());
        $this->postJson($this->url)->assertNotFound();

        $this->postJson($this->url)->assertNotFound();
        $this->assertNull($this->work->fresh()->last_observed_at);
    }

    public function test_ping_on_a_non_member_or_a_student_without_work_is_a_404(): void
    {
        $this->enableTracking();
        Sanctum::actingAs($this->teacher);

        // Pas membre de la classe.
        $this->postJson("/api/assignments/{$this->assignment->id}/live/{$this->outsider->id}/seen")->assertNotFound();

        // Membre, mais qui n'a pas commencé : rien à marquer.
        $bob = User::factory()->create();
        $this->classroom->members()->attach($bob->id);
        $this->postJson("/api/assignments/{$this->assignment->id}/live/{$bob->id}/seen")->assertNotFound();
    }

    public function test_ping_requires_a_token(): void
    {
        $this->enableTracking();

        $this->postJson($this->url)->assertUnauthorized();
    }
}
