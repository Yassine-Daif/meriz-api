<?php

namespace App\Actions\Assignments;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use Illuminate\Validation\ValidationException;

/**
 * Publication et libération du corrigé. Ces champs ne s'écrivent qu'ici.
 */
class ChangeAssignmentState
{
    public const NO_SOLUTION_MESSAGE = 'Ce devoir n\'a pas de corrigé à libérer.';

    public function publish(Assignment $assignment): Assignment
    {
        if (! $assignment->isPublished()) {
            $assignment->forceFill([
                'status' => AssignmentStatus::Published,
                'published_at' => now(),
            ])->save();
        }

        return $assignment;
    }

    public function unpublish(Assignment $assignment): Assignment
    {
        $assignment->forceFill([
            'status' => AssignmentStatus::Draft,
            'published_at' => null,
        ])->save();

        return $assignment;
    }

    /**
     * @throws ValidationException
     */
    public function releaseSolution(Assignment $assignment): Assignment
    {
        if (! $assignment->hasSolution()) {
            throw ValidationException::withMessages(['solution_content' => self::NO_SOLUTION_MESSAGE]);
        }

        if (! $assignment->solutionReleased()) {
            $assignment->forceFill(['solution_released_at' => now()])->save();
        }

        return $assignment;
    }

    public function withholdSolution(Assignment $assignment): Assignment
    {
        $assignment->forceFill(['solution_released_at' => null])->save();

        return $assignment;
    }
}
