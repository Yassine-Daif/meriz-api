<?php

namespace Database\Factories;

use App\Enums\LessonStatus;
use App\Models\Classroom;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'title' => 'Cours '.fake()->bothify('?#'),
            'blocks' => json_encode([
                ['type' => 'heading', 'text' => 'Introduction'],
                ['type' => 'text', 'text' => 'Un MCD décrit les données et leurs liens.'],
            ]),
            'status' => LessonStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LessonStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function withBlocks(string $blocks): static
    {
        return $this->state(fn (array $attributes) => ['blocks' => $blocks]);
    }
}
