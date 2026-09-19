<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Accès aux devoirs.
 *
 * Le prof de la classe voit et gère tout. Un membre ne voit que les devoirs
 * publiés, et le corrigé seulement une fois libéré. Tout autre compte, et un
 * membre face à un brouillon, reçoit un 404 : on ne révèle pas l'existence.
 */
class AssignmentPolicy
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

    public function view(User $user, Assignment $assignment): Response
    {
        return $this->canView($user, $assignment)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Modifier, publier, libérer le corrigé, gérer l'image, supprimer.
     */
    public function manage(User $user, Assignment $assignment): Response
    {
        if ($assignment->classroom->isTaughtBy($user)) {
            return Response::allow();
        }

        // Un membre qui voit le devoir sait qu'il existe : 403. Sinon 404.
        return $this->canView($user, $assignment)
            ? Response::deny(self::OWNER_ONLY_MESSAGE)
            : Response::denyAsNotFound();
    }

    /**
     * Le corrigé : le prof toujours, un membre seulement si le devoir est
     * publié et le corrigé libéré.
     */
    public function viewSolution(User $user, Assignment $assignment): bool
    {
        if ($assignment->classroom->isTaughtBy($user)) {
            return true;
        }

        return $assignment->isPublished()
            && $assignment->solutionReleased()
            && $assignment->classroom->hasMember($user);
    }

    public function copyBase(User $user, Assignment $assignment): Response
    {
        return $this->view($user, $assignment);
    }

    private function canView(User $user, Assignment $assignment): bool
    {
        if ($assignment->classroom->isTaughtBy($user)) {
            return true;
        }

        return $assignment->isPublished() && $assignment->classroom->hasMember($user);
    }
}
