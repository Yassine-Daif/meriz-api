<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Accès aux classes.
 *
 * Qui n'est ni le prof ni un membre reçoit un 404 : on ne révèle pas que la
 * classe existe. Un membre qui tente de gérer reçoit un 403, car il connaît
 * déjà la classe.
 */
class ClassroomPolicy
{
    public const TEACHER_ONLY_MESSAGE = 'Seul un compte prof peut créer une classe.';

    public const OWNER_ONLY_MESSAGE = 'Réservé au prof de la classe.';

    public const OWNER_CANNOT_LEAVE_MESSAGE = 'Le prof ne peut pas quitter sa propre classe.';

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): Response
    {
        return $user->isTeacher()
            ? Response::allow()
            : Response::deny(self::TEACHER_ONLY_MESSAGE);
    }

    public function view(User $user, Classroom $classroom): Response
    {
        return $classroom->isTaughtBy($user) || $classroom->hasMember($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Renommer, régénérer le code, supprimer, retirer un membre.
     */
    public function manage(User $user, Classroom $classroom): Response
    {
        if ($classroom->isTaughtBy($user)) {
            return Response::allow();
        }

        return $classroom->hasMember($user)
            ? Response::deny(self::OWNER_ONLY_MESSAGE)
            : Response::denyAsNotFound();
    }

    public function leave(User $user, Classroom $classroom): Response
    {
        if ($classroom->isTaughtBy($user)) {
            return Response::deny(self::OWNER_CANNOT_LEAVE_MESSAGE);
        }

        return $classroom->hasMember($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
