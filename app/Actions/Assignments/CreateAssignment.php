<?php

namespace App\Actions\Assignments;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Classroom;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crée un devoir en brouillon dans une classe déjà autorisée pour son prof.
 */
class CreateAssignment
{
    public const QUOTA_MESSAGE = 'Nombre maximal de devoirs atteint pour cette classe.';

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(Classroom $classroom, array $data): Assignment
    {
        return DB::transaction(function () use ($classroom, $data) {
            // Verrou sur la classe : deux créations simultanées ne dépassent pas le quota.
            Classroom::whereKey($classroom->id)->lockForUpdate()->first();

            if ($classroom->assignments()->count() >= config('assignments.max_per_classroom')) {
                throw ValidationException::withMessages(['title' => self::QUOTA_MESSAGE]);
            }

            // La classe vient de la route, jamais des données du client.
            $assignment = $classroom->assignments()->make($data);
            $assignment->forceFill(['status' => AssignmentStatus::Draft])->save();

            return $assignment;
        });
    }
}
