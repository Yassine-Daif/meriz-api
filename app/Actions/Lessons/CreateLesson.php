<?php

namespace App\Actions\Lessons;

use App\Enums\LessonStatus;
use App\Models\Classroom;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crée un cours en brouillon dans une classe déjà autorisée pour son prof.
 */
class CreateLesson
{
    public const QUOTA_MESSAGE = 'Nombre maximal de cours atteint pour cette classe.';

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(Classroom $classroom, array $data): Lesson
    {
        return DB::transaction(function () use ($classroom, $data) {
            Classroom::whereKey($classroom->id)->lockForUpdate()->first();

            if ($classroom->lessons()->count() >= config('lessons.max_per_classroom')) {
                throw ValidationException::withMessages(['title' => self::QUOTA_MESSAGE]);
            }

            // La classe vient de la route, jamais des données du client.
            $lesson = $classroom->lessons()->make($data);
            $lesson->forceFill(['status' => LessonStatus::Draft])->save();

            return $lesson;
        });
    }
}
