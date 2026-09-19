<?php

namespace App\Actions\Assignments;

use App\Models\Assignment;

class UpdateAssignment
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Assignment $assignment, array $data): Assignment
    {
        $assignment->fill($data);

        // Retirer le corrigé annule aussi sa libération : un futur corrigé
        // ne doit pas devenir visible sans nouvelle décision du prof.
        if (array_key_exists('solution_content', $data) && $data['solution_content'] === null) {
            $assignment->forceFill(['solution_released_at' => null]);
        }

        $assignment->save();

        return $assignment;
    }
}
