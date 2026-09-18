<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\User;
use App\Services\JoinCodeGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_id' => User::factory()->teacher(),
            'name' => 'Classe '.fake()->bothify('?#'),
            'join_code' => app(JoinCodeGenerator::class)->generate(),
        ];
    }
}
