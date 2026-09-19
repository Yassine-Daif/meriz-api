<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Models\Assignment;
use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'title' => fake()->sentence(4),
            'instructions' => fake()->paragraph(),
            'type' => AssignmentType::Exercise,
            'status' => AssignmentStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssignmentStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function withBase(string $content = '{"model":{"base":true}}'): static
    {
        return $this->state(fn (array $attributes) => ['base_content' => $content]);
    }

    public function withSolution(string $content = '{"model":{"solution":true}}'): static
    {
        return $this->state(fn (array $attributes) => ['solution_content' => $content]);
    }

    public function solutionReleased(): static
    {
        return $this->withSolution()->state(fn (array $attributes) => ['solution_released_at' => now()]);
    }
}
