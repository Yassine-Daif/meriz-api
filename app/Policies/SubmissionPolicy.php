<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use App\Policies\Concerns\DecidesAccess;
use Illuminate\Auth\Access\Response;

class SubmissionPolicy
{
    use DecidesAccess;

    public const LOCKED_MESSAGE = 'Ce rendu est noté, il n\'est plus modifiable.';

    public const TEACHER_ONLY_MESSAGE = 'Réservé au prof du devoir.';

    public const STUDENT_ONLY_MESSAGE = 'Seul un élève de la classe peut rendre un travail.';

    /**
     * Rendre un travail : trois refus de natures différentes, donc pas le
     * motif habituel. Le prof est refusé explicitement, l'étranger est
     * caché, et le rendu déjà noté est verrouillé.
     */
    public function submit(User $user, Assignment $assignment): Response
    {
        if ($assignment->classroom->isTaughtBy($user)) {
            return Response::deny(self::STUDENT_ONLY_MESSAGE);
        }

        if (! $assignment->isPublished() || ! $assignment->classroom->hasMember($user)) {
            return Response::denyAsNotFound();
        }

        $existing = $assignment->submissions()->where('user_id', $user->id)->first();

        return $existing?->isGraded()
            ? Response::deny(self::LOCKED_MESSAGE)
            : Response::allow();
    }

    public function view(User $user, Submission $submission): Response
    {
        // En deux temps, exprès : pour l'auteur, on ne charge pas le devoir
        // et sa classe pour rien.
        if ($submission->user_id === $user->id) {
            return Response::allow();
        }

        return $this->allowOrHide($submission->assignment->classroom->isTaughtBy($user));
    }

    public function viewAny(User $user, Assignment $assignment): Response
    {
        return $this->reserve(
            $assignment->classroom->isTaughtBy($user),
            fn () => $assignment->isPublished() && $assignment->classroom->hasMember($user),
            self::TEACHER_ONLY_MESSAGE,
        );
    }

    public function grade(User $user, Submission $submission): Response
    {
        return $this->reserve(
            $submission->assignment->classroom->isTaughtBy($user),
            fn () => $submission->user_id === $user->id,
            self::TEACHER_ONLY_MESSAGE,
        );
    }
}
