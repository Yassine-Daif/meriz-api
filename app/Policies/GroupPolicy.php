<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use App\Policies\Concerns\DecidesAccess;
use Illuminate\Auth\Access\Response;

class GroupPolicy
{
    use DecidesAccess;

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
        return $this->allowOrHide($this->belongs($user, $group));
    }

    public function manage(User $user, Group $group): Response
    {
        return $this->reserve(
            $group->isAdministeredBy($user),
            fn () => $group->hasMember($user),
            self::CREATOR_ONLY_MESSAGE,
        );
    }

    /**
     * Quitter un groupe : un membre peut, le créateur non — il le supprime.
     */
    public function leave(User $user, Group $group): Response
    {
        if ($group->isAdministeredBy($user)) {
            return Response::deny(self::CREATOR_CANNOT_LEAVE_MESSAGE);
        }

        return $this->allowOrHide($group->hasMember($user));
    }

    public function createDocument(User $user, Group $group): Response
    {
        return $this->allowOrHide($this->belongs($user, $group));
    }

    private function belongs(User $user, Group $group): bool
    {
        return $group->isAdministeredBy($user) || $group->hasMember($user);
    }
}
