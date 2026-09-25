<?php

namespace App\Http\Resources;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une ligne de la file de correction du prof : de quoi afficher et ouvrir.
 *
 * Ni le contenu du rendu, ni la note, ni le corrigé. L'élève est rendu en
 * profil public, donc jamais d'email de connexion.
 *
 * @mixin Submission
 */
class ToGradeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assignment = $this->assignment;

        return [
            'submission_id' => $this->id,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'is_late' => $this->isLate(),
            'student' => new PublicProfileResource($this->whenLoaded('student')),
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'type' => $assignment->type->value,
                'due_at' => $assignment->due_at?->toIso8601String(),
            ],
            'classroom' => [
                'id' => $assignment->classroom->id,
                'name' => $assignment->classroom->name,
            ],
        ];
    }
}
