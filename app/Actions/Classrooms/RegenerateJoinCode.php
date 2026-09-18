<?php

namespace App\Actions\Classrooms;

use App\Models\Classroom;
use App\Services\JoinCodeGenerator;

/**
 * Remplace le code d'une classe. L'ancien cesse aussitôt de fonctionner.
 */
class RegenerateJoinCode
{
    public function __construct(private readonly JoinCodeGenerator $codes) {}

    public function handle(Classroom $classroom): Classroom
    {
        $classroom->forceFill(['join_code' => $this->codes->generate()])->save();

        return $classroom;
    }
}
