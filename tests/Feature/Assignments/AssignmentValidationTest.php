<?php

namespace Tests\Feature\Assignments;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssignmentValidationTest extends TestCase
{
    use RefreshDatabase;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();

        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->create();
        $this->url = "/api/classrooms/{$classroom->id}/assignments";
        Sanctum::actingAs($teacher);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['title' => 'Titre', 'instructions' => 'Consigne', 'type' => 'exercise'], $overrides);
    }

    public function test_required_fields(): void
    {
        $this->postJson($this->url, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'instructions', 'type']);
    }

    public function test_lengths_type_and_date(): void
    {
        $this->postJson($this->url, $this->payload([
            'title' => str_repeat('a', 201),
            'instructions' => str_repeat('a', 20001),
            'type' => 'devoir-maison',
            'due_at' => 'demain',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'instructions', 'type', 'due_at']);

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_base_and_solution_must_be_json_strings_of_bounded_size(): void
    {
        config(['documents.max_content_bytes' => 50]);

        $this->postJson($this->url, $this->payload([
            'base_content' => 'pas du json',
            'solution_content' => ['objet' => 'au lieu de chaine'],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['base_content', 'solution_content']);

        $this->postJson($this->url, $this->payload([
            'solution_content' => '"'.str_repeat('a', 60).'"',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['solution_content']);

        $this->postJson($this->url, $this->payload(['base_content' => '{"ok":1}']))->assertCreated();
    }
}
