<?php

namespace App\Actions\Submissions;

use App\Enums\SubmissionStatus;
use App\Models\Submission;

/**
 * Note un rendu, ou retire la note. Ces champs ne s'écrivent qu'ici.
 */
class GradeSubmission
{
    public function grade(Submission $submission, string $grade, ?string $feedback): Submission
    {
        $submission->forceFill([
            'grade' => $grade,
            'feedback' => $feedback,
            'graded_at' => now(),
            'status' => SubmissionStatus::Graded,
        ])->save();

        return $submission;
    }

    /**
     * Retire la note : le rendu redevient modifiable par l'élève.
     */
    public function remove(Submission $submission): Submission
    {
        $submission->forceFill([
            'grade' => null,
            'feedback' => null,
            'graded_at' => null,
            'status' => SubmissionStatus::Submitted,
        ])->save();

        return $submission;
    }
}
