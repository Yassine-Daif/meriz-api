<?php

namespace Database\Factories;

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
}
