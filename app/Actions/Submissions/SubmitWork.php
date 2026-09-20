<?php

namespace App\Actions\Submissions;

use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use App\Policies\SubmissionPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Remet ou met à jour le rendu de l'élève authentifié.
 *
 * L'auteur vient toujours du jeton. Un rendu déjà noté est verrouillé.
 */
class SubmitWork
{
    /**
     * @return array{submission: Submission, created: bool}
     *
     * @throws AuthorizationException
     */
    public function handle(User $student, Assignment $assignment, string $content): array
    {
        return DB::transaction(function () use ($student, $assignment, $content) {
            $submission = Submission::where('assignment_id', $assignment->id)
                ->where('user_id', $student->id)
                ->lockForUpdate()
                ->first();

            if ($submission?->isGraded()) {
                throw new AuthorizationException(SubmissionPolicy::LOCKED_MESSAGE);
            }

            $created = $submission === null;
            $submission ??= new Submission;

            $submission->fill(['content' => $content])->forceFill([
                'assignment_id' => $assignment->id,
                'user_id' => $student->id,
                'status' => SubmissionStatus::Submitted,
                'submitted_at' => now(),
            ])->save();

            return ['submission' => $submission, 'created' => $created];
        });
    }
}
