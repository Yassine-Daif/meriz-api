<?php

namespace App\Http\Resources;

use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un devoir vu depuis le tableau de bord de l'élève, avec l'état de son
 * travail. Ni consigne, ni base, ni corrigé : la vue détaillée du devoir
 * s'en charge.
 *
 * @mixin Assignment
 */
class StudentAssignmentResource extends JsonResource
{
    public const STATE_TODO = 'todo';

    public const STATE_IN_PROGRESS = 'in_progress';

    public const STATE_SUBMITTED = 'submitted';

    public const STATE_GRADED = 'graded';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // La relation n'est chargée qu'avec le rendu de l'élève connecté.
        $submission = $this->submissions->first();
        $documentId = $this->my_document_id;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type->value,
            'due_at' => $this->due_at?->toIso8601String(),
            'is_overdue' => $this->due_at !== null && $this->due_at->isPast(),
            'classroom' => [
                'id' => $this->classroom->id,
                'name' => $this->classroom->name,
            ],
            'has_base' => $this->hasBase(),
            'has_image' => $this->hasImage(),
            'state' => $this->state($submission, $documentId),
            // Le travail en cours à rouvrir, s'il existe.
            'document_id' => $documentId,
            'submission' => $submission === null ? null : [
                'id' => $submission->id,
                'status' => $submission->status->value,
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
                'is_late' => $this->due_at !== null && $submission->submitted_at?->greaterThan($this->due_at),
                // Sa propre note, donc visible.
                'grade' => $submission->grade,
                'feedback' => $submission->feedback,
                'graded_at' => $submission->graded_at?->toIso8601String(),
            ],
        ];
    }

    private function state(?Submission $submission, ?string $documentId): string
    {
        if ($submission?->isGraded()) {
            return self::STATE_GRADED;
        }

        if ($submission !== null) {
            return self::STATE_SUBMITTED;
        }

        return $documentId === null ? self::STATE_TODO : self::STATE_IN_PROGRESS;
    }
}
