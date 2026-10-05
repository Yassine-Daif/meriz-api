<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Accès aux groupes.
 *
 * Le créateur est l'administrateur : lui seul renomme, régénère le code,
 * retire un membre et supprime. Un non-membre reçoit un 404 : on ne révèle
 * pas qu'un groupe existe.
 */
class GroupPolicy
{
    public const CREATOR_ONLY_MESSAGE = 'Réservé au créateur du groupe.';

    public const CREATOR_CANNOT_LEAVE_MESSAGE = 'Le créateur ne peut pas quitter son groupe, il peut le supprimer.';

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Group $group): Response
    {
        return $this->belongs($user, $group)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Renommer, régénérer le code, retirer un membre, supprimer.
     */
    public function manage(User $user, Group $group): Response
    {
        if ($group->isAdministeredBy($user)) {
            return Response::allow();
        }

        // Un membre sait que le groupe existe : 403. Sinon 404.
        return $group->hasMember($user)
            ? Response::deny(self::CREATOR_ONLY_MESSAGE)
            : Response::denyAsNotFound();
    }

    public function leave(User $user, Group $group): Response
    {
        if ($group->isAdministeredBy($user)) {
            return Response::deny(self::CREATOR_CANNOT_LEAVE_MESSAGE);
        }

        return $group->hasMember($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Créer un document dans l'espace du groupe : tout membre.
     */
    public function createDocument(User $user, Group $group): Response
    {
        return $this->belongs($user, $group)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    private function belongs(User $user, Group $group): bool
    {
        return $group->isAdministeredBy($user) || $group->hasMember($user);
    }
}
