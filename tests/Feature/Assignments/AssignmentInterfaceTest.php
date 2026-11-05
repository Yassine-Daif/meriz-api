<?php

namespace Tests\Feature\Assignments;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssignmentInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): array
    {
        return ['views' => ['dictionnaire', 'mcd'], 'export_image' => true, 'export_model' => false];
    }

    public function test_owner_changes_settings_and_publishes_only_lightweight_metadata(): void
    {
        $assignment = Assignment::factory()->published()->create();
        Sanctum::actingAs($assignment->classroom->teacher);
        $this->patchJson('/api/assignments/'.$assignment->id, ['interface_settings' => $this->settings()])
            ->assertOk()->assertJsonPath('data.interface_settings.views', ['dictionnaire', 'mcd']);
        $this->getJson('/api/assignments/'.$assignment->id.'/interface-settings')
            ->assertOk()->assertExactJson(['data' => ['interface_settings' => $this->settings()]]);
        $this->patchJson('/api/assignments/'.$assignment->id, ['title' => 'Autre titre'])
            ->assertOk()->assertJsonPath('data.interface_settings', $this->settings());
        $this->patchJson('/api/assignments/'.$assignment->id, ['interface_settings' => null])
            ->assertOk()->assertJsonPath('data.interface_settings', null);
    }

    public function test_member_can_read_but_cannot_change_the_settings(): void
    {
        $assignment = Assignment::factory()->published()->create(['interface_settings' => $this->settings()]);
        $student = User::factory()->create();
        $assignment->classroom->members()->attach($student);
        Sanctum::actingAs($student);
        $this->getJson('/api/assignments/'.$assignment->id.'/interface-settings')->assertOk();
        $this->patchJson('/api/assignments/'.$assignment->id, ['interface_settings' => null])->assertForbidden();
        $this->assertSame($this->settings(), $assignment->fresh()->interface_settings);
    }

    public function test_another_teacher_cannot_read_or_change_settings(): void
    {
        $assignment = Assignment::factory()->published()->create();
        Sanctum::actingAs(User::factory()->teacher()->create());
        $this->getJson('/api/assignments/'.$assignment->id.'/interface-settings')->assertNotFound();
        $this->patchJson('/api/assignments/'.$assignment->id, ['interface_settings' => null])->assertNotFound();
    }

    public function test_partial_unknown_and_empty_settings_are_rejected(): void
    {
        $assignment = Assignment::factory()->create();
        Sanctum::actingAs($assignment->classroom->teacher);
        foreach ([['views' => ['mcd']], ['views' => []], [...$this->settings(), 'views' => ['admin']], [...$this->settings(), 'views' => ['mcd', 'mcd']], [...$this->settings(), 'role' => 'teacher']] as $settings) {
            $this->patchJson('/api/assignments/'.$assignment->id, ['interface_settings' => $settings])->assertUnprocessable();
        }
    }
}
