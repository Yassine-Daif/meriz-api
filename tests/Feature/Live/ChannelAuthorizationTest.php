<?php

namespace Tests\Feature\Live;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * L'abonnement au canal d'observation passe par la même règle que la lecture
 * HTTP : AssignmentPolicy::observeLive. On le prouve ici en appelant la route
 * d'autorisation des canaux avec un jeton.
 */
class ChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $alice;

    private User $bob;

    private User $outsider;

    private Classroom $classroom;

    private Assignment $assignment;

    private Document $work;

    protected function setUp(): void
    {
        parent::setUp();

        // Les tests tournent par défaut sur le diffuseur « null », qui accepte
        // tout sans consulter les canaux. On bascule donc sur un vrai
        // diffuseur, avec des identifiants factices : la signature se calcule
        // en local, sans réseau, et l'autorisation du canal est bien jouée.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'cle-de-test',
            'broadcasting.connections.reverb.secret' => 'secret-de-test',
            'broadcasting.connections.reverb.app_id' => 'meriz-test',
        ]);

        // Les canaux s'enregistrent sur le diffuseur actif au démarrage. Après
        // la bascule, on recharge le vrai fichier de canaux, pas une copie.
        require base_path('routes/channels.php');

        $this->teacher = User::factory()->teacher()->create();
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->classroom = Classroom::factory()->for($this->teacher, 'teacher')->create();
        $this->classroom->members()->attach([$this->alice->id, $this->bob->id]);

        $this->assignment = Assignment::factory()->for($this->classroom)->published()->withBase()->create();
        $this->work = Document::factory()->for($this->alice)->forAssignment($this->assignment)->create();
    }

    private function channel(?string $assignmentId = null, ?int $studentId = null): string
    {
        return 'private-assignments.'.($assignmentId ?? $this->assignment->id)
            .'.work.'.($studentId ?? $this->alice->id);
    }

    private function subscribe(string $channel)
    {
        return $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => $channel,
        ]);
    }

    private function enableTracking(): void
    {
        $this->assignment->forceFill(['live_tracking' => true, 'live_tracking_enabled_at' => now()])->save();
    }

    public function test_owner_teacher_can_subscribe_when_tracking_is_on(): void
    {
        $this->enableTracking();
        Sanctum::actingAs($this->teacher);

        $this->subscribe($this->channel())->assertOk();

        // S'abonner, c'est observer : la trace est écrite pour l'élève.
        $this->assertNotNull($this->work->fresh()->last_observed_at);
    }

    public function test_subscription_is_refused_while_tracking_is_off(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->subscribe($this->channel())->assertForbidden();

        $this->assertNull($this->work->fresh()->last_observed_at);
    }

    public function test_students_can_never_subscribe(): void
    {
        $this->enableTracking();

        // Ni au canal d'un camarade…
        Sanctum::actingAs($this->bob);
        $this->subscribe($this->channel())->assertForbidden();

        // … ni au sien.
        Sanctum::actingAs($this->alice);
        $this->subscribe($this->channel(studentId: $this->alice->id))->assertForbidden();

        $this->assertNull($this->work->fresh()->last_observed_at);
    }

    public function test_another_teacher_and_outsiders_are_refused(): void
    {
        $this->enableTracking();

        foreach ([User::factory()->teacher()->create(), $this->outsider] as $intruder) {
            Sanctum::actingAs($intruder);
            $this->subscribe($this->channel())->assertForbidden();
        }

        $this->assertNull($this->work->fresh()->last_observed_at);
    }

    public function test_target_must_be_a_member_of_the_classroom(): void
    {
        $this->enableTracking();
        Sanctum::actingAs($this->teacher);

        $this->subscribe($this->channel(studentId: $this->outsider->id))->assertForbidden();
        $this->subscribe($this->channel(studentId: $this->teacher->id))->assertForbidden();
        $this->subscribe($this->channel(studentId: 999999))->assertForbidden();
    }

    public function test_a_former_member_channel_is_refused(): void
    {
        $this->enableTracking();
        Sanctum::actingAs($this->teacher);

        $this->subscribe($this->channel())->assertOk();

        $this->classroom->members()->detach($this->alice->id);

        $this->subscribe($this->channel())->assertForbidden();
    }

    public function test_unknown_assignment_is_refused(): void
    {
        $this->enableTracking();
        Sanctum::actingAs($this->teacher);

        $this->subscribe($this->channel(assignmentId: (string) Str::ulid()))->assertForbidden();
        $this->subscribe($this->channel(assignmentId: 'pas-un-id'))->assertForbidden();

        // Devoir d'un autre prof, suivi activé chez lui.
        $foreign = Assignment::factory()->published()->create(['live_tracking' => true]);
        $this->subscribe($this->channel(assignmentId: $foreign->id))->assertForbidden();
    }

    public function test_subscription_requires_a_token(): void
    {
        $this->enableTracking();

        $this->subscribe($this->channel())->assertUnauthorized();

        $this->assertNull($this->work->fresh()->last_observed_at);
    }
}
