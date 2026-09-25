<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'content' => json_encode([
                'model' => ['title' => fake()->words(2, true)],
                'positions' => [],
                'settings' => ['zoom' => 1],
            ]),
        ];
    }

    /**
     * Document issu de la base d'un devoir.
     */
    public function forAssignment(Assignment $assignment): static
    {
        return $this->state(fn (array $attributes) => ['assignment_id' => $assignment->id]);
    }
}
