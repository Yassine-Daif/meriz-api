<?php

namespace Database\Factories;

use App\Enums\MediaKind;
use App\Models\Lesson;
use App\Models\LessonMedium;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LessonMedium>
 */
class LessonMediumFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'kind' => MediaKind::Image,
            'path' => 'lessons/fake/'.Str::random(40).'.png',
            'mime' => 'image/png',
            'size' => 1024,
            'name' => 'schema.png',
        ];
    }

    public function audio(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => MediaKind::Audio,
            'path' => 'lessons/fake/'.Str::random(40).'.mp3',
            'mime' => 'audio/mpeg',
            'name' => 'explication.mp3',
        ]);
    }
}
