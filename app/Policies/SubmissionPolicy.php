<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Accès aux rendus.
 *
 * L'élève ne touche que le sien, le prof du devoir voit et note ceux de son
 * devoir. Qui n'a rien à y voir reçoit un 404.
 */
class SubmissionPolicy
{
    public const LOCKED_MESSAGE = 'Ce rendu est noté, il n\'est plus modifiable.';

    public const TEACHER_ONLY_MESSAGE = 'Réservé au prof du devoir.';

    public const STUDENT_ONLY_MESSAGE = 'Seul un élève de la classe peut rendre un travail.';

    /**
     * Remettre ou mettre à jour son rendu : membre de la classe, devoir
     * publié, et rendu pas encore noté.
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
        if ($submission->user_id === $user->id) {
            return Response::allow();
        }

        return $submission->assignment->classroom->isTaughtBy($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Lister les rendus d'un devoir : son prof seulement.
     */
    public function viewAny(User $user, Assignment $assignment): Response
    {
        if ($assignment->classroom->isTaughtBy($user)) {
            return Response::allow();
        }

        // Un membre qui voit le devoir sait qu'il existe : 403. Sinon 404.
        return $assignment->isPublished() && $assignment->classroom->hasMember($user)
            ? Response::deny(self::TEACHER_ONLY_MESSAGE)
            : Response::denyAsNotFound();
    }

    /**
     * Noter et retirer la note : le prof du devoir seulement.
     */
    public function grade(User $user, Submission $submission): Response
    {
        if ($submission->assignment->classroom->isTaughtBy($user)) {
            return Response::allow();
        }

        return $submission->user_id === $user->id
            ? Response::deny(self::TEACHER_ONLY_MESSAGE)
            : Response::denyAsNotFound();
    }
}
