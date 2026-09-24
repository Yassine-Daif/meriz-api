<?php

namespace App\Actions\Lessons;

use App\Enums\LessonStatus;
use App\Models\Lesson;

/**
 * Publication d'un cours. Ces champs ne s'écrivent qu'ici.
 */
class ChangeLessonState
{
    public function publish(Lesson $lesson): Lesson
    {
        if (! $lesson->isPublished()) {
            $lesson->forceFill([
                'status' => LessonStatus::Published,
                'published_at' => now(),
            ])->save();
        }

        return $lesson;
    }

    public function unpublish(Lesson $lesson): Lesson
    {
        $lesson->forceFill([
            'status' => LessonStatus::Draft,
            'published_at' => null,
        ])->save();

        return $lesson;
    }
}
