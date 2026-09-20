<?php

namespace App\Http\Resources;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un rendu, vu par son auteur ou par le prof du devoir.
 *
 * L'élève est rendu en profil public : jamais d'email de connexion. Le
 * contenu du travail ne sort qu'en vue détaillée, pas dans les listes.
 *
 * @mixin Submission
 */
class SubmissionResource extends JsonResource
{
    private bool $detailed = false;

    public function detailed(): static
    {
        $this->detailed = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_id' => $this->assignment_id,
            'student' => new PublicProfileResource($this->whenLoaded('student')),
            'status' => $this->status->value,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'is_late' => $this->isLate(),
            'grade' => $this->grade,
            'feedback' => $this->feedback,
            'graded_at' => $this->graded_at?->toIso8601String(),
            'content' => $this->when($this->detailed, fn () => $this->content),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
