<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Accès aux cours.
 *
 * Le prof de la classe voit et gère tout. Un membre ne voit que les cours
 * publiés. Tout autre compte, et un membre face à un brouillon, reçoit 404.
 */
class LessonPolicy
{
    public const OWNER_ONLY_MESSAGE = 'Réservé au prof de la classe.';

    public function create(User $user, Classroom $classroom): Response
    {
        if ($classroom->isTaughtBy($user)) {
            return Response::allow();
        }

        return $classroom->hasMember($user)
            ? Response::deny(self::OWNER_ONLY_MESSAGE)
            : Response::denyAsNotFound();
    }

    public function view(User $user, Lesson $lesson): Response
    {
        return $this->canView($user, $lesson)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Modifier, publier, gérer les médias, supprimer.
     */
    public function manage(User $user, Lesson $lesson): Response
    {
        if ($lesson->classroom->isTaughtBy($user)) {
            return Response::allow();
        }

        // Un membre qui voit le cours sait qu'il existe : 403. Sinon 404.
        return $this->canView($user, $lesson)
            ? Response::deny(self::OWNER_ONLY_MESSAGE)
            : Response::denyAsNotFound();
    }

    private function canView(User $user, Lesson $lesson): bool
    {
        if ($lesson->classroom->isTaughtBy($user)) {
            return true;
        }

        return $lesson->isPublished() && $lesson->classroom->hasMember($user);
    }
}
