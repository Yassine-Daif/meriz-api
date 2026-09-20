<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory()->published(),
            'user_id' => User::factory(),
            'content' => '{"model":{"rendu":true}}',
            'submitted_at' => now(),
            'status' => SubmissionStatus::Submitted,
        ];
    }

    public function graded(string $grade = '16/20', ?string $feedback = 'Bon travail.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubmissionStatus::Graded,
            'grade' => $grade,
            'feedback' => $feedback,
            'graded_at' => now(),
        ]);
    }

    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'submitted_at' => now()->addWeek(),
        ]);
    }
}
