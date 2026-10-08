<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'user_id' => User::factory(),
            'body' => 'Attention à la cardinalité de cette association.',
        ];
    }

    /**
     * Bulle posée à un endroit du schéma.
     */
    public function at(float $x, float $y): static
    {
        return $this->state(fn (array $attributes) => [
            'position_x' => $x,
            'position_y' => $y,
        ]);
    }

    public function resolved(?User $by = null): static
    {
        return $this->state(fn (array $attributes) => [
            'resolved_at' => now(),
            'resolved_by' => $by?->id,
        ]);
    }
}
