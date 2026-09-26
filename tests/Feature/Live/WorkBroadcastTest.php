<?php

namespace Tests\Feature\Live;

use App\Events\WorkUpdated;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Document;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private const SOLUTION_MARKER = 'CORRIGE-WS-8d2c';

    private User $teacher;

    private User $alice;

    private Assignment $assignment;

    private Document $work;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
        $this->alice = User::factory()->create(['email' => 'alice.secret@gmail.com']);

        $classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $classroom->members()->attach($this->alice->id);

        $this->assignment = Assignment::factory()->for($classroom)->published()->withBase()
            ->withSolution('{"corrige":"'.self::SOLUTION_MARKER.'"}')
            ->create();

        $this->work = Document::factory()->for($this->alice)->forAssignment($this->assignment)->create();

        Sanctum::actingAs($this->alice);
    }

    private function enableTracking(): void
    {
        $this->assignment->forceFill(['live_tracking' => true, 'live_tracking_enabled_at' => now()])->save();
    }

    private function save(string $content): void
    {
        $this->patchJson("/api/documents/{$this->work->id}", ['content' => $content])->assertOk();
    }

    public function test_saving_broadcasts_on_the_students_private_channel(): void
    {
        $this->enableTracking();
        Event::fake([WorkUpdated::class]);

        $this->save('{"etape":2}');

        Event::assertDispatched(WorkUpdated::class, function (WorkUpdated $event) {
            $channels = $event->broadcastOn();

            return $event->document->is($this->work)
                && $event->broadcastWhen()
                && $channels[0] instanceof PrivateChannel
                && $channels[0]->name === "private-assignments.{$this->assignment->id}.work.{$this->alice->id}";
        });
    }

    public function test_nothing_is_broadcast_while_tracking_is_off(): void
    {
        Event::fake([WorkUpdated::class]);

        $this->save('{"etape":2}');

        // L'évènement est bien créé, mais broadcastWhen l'empêche de partir.
        Event::assertDispatched(WorkUpdated::class, fn (WorkUpdated $event) => $event->broadcastWhen() === false);
    }

    public function test_a_personal_document_never_broadcasts(): void
    {
        $this->enableTracking();
        $personal = Document::factory()->for($this->alice)->create();
        Event::fake([WorkUpdated::class]);

        $this->patchJson("/api/documents/{$personal->id}", ['content' => '{"prive":1}'])->assertOk();

        Event::assertNotDispatched(WorkUpdated::class);
    }

    public function test_payload_carries_the_snapshot_and_nothing_else(): void
    {
        $this->enableTracking();
        $content = '{"model":{},"zoom":1.0,"t":"é\\u00e9"}';
        $this->save($content);

        $payload = (new WorkUpdated($this->work->fresh()))->broadcastWith();

        $this->assertSame([
            'document_id', 'assignment_id', 'student_id', 'name', 'content', 'updated_at',
        ], array_keys($payload));

        $this->assertSame($content, $payload['content']);
        $this->assertSame($this->alice->id, $payload['student_id']);

        $encoded = json_encode($payload);
        $this->assertStringNotContainsString(self::SOLUTION_MARKER, $encoded);
        $this->assertStringNotContainsString('alice.secret@gmail.com', $encoded);
        $this->assertStringNotContainsString('grade', $encoded);
    }

    public function test_the_channel_belongs_to_the_author_not_another_student(): void
    {
        $this->enableTracking();
        $bob = User::factory()->create();
        $this->assignment->classroom->members()->attach($bob->id);
        $bobWork = Document::factory()->for($bob)->forAssignment($this->assignment)->create();

        $aliceChannel = (new WorkUpdated($this->work))->broadcastOn()[0]->name;
        $bobChannel = (new WorkUpdated($bobWork))->broadcastOn()[0]->name;

        $this->assertNotSame($aliceChannel, $bobChannel);
        $this->assertStringEndsWith((string) $this->alice->id, $aliceChannel);
        $this->assertStringEndsWith((string) $bob->id, $bobChannel);
    }

    public function test_broadcasting_does_not_change_the_document(): void
    {
        $this->enableTracking();
        Event::fake([WorkUpdated::class]);

        $this->save('{"etape":3}');

        $fresh = $this->work->fresh();
        $this->assertSame('{"etape":3}', $fresh->content);
        // La diffusion n'écrit pas de trace de lecture : personne n'a encore lu.
        $this->assertNull($fresh->last_observed_at);
    }
}
