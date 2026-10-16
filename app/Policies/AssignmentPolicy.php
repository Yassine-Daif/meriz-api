<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\User;
use App\Policies\Concerns\DecidesAccess;
use Illuminate\Auth\Access\Response;

class AssignmentPolicy
{
    use DecidesAccess;

    public const OWNER_ONLY_MESSAGE = 'Réservé au prof de la classe.';

    public const TRACKING_OFF_MESSAGE = 'Le suivi en direct n\'est pas activé pour ce devoir.';

    public function create(User $user, Classroom $classroom): Response
    {
        return $this->reserve(
            $classroom->isTaughtBy($user),
            fn () => $classroom->hasMember($user),
            self::OWNER_ONLY_MESSAGE,
        );
    }

    public function view(User $user, Assignment $assignment): Response
    {
        return $this->allowOrHide($this->canView($user, $assignment));
    }

    public function manage(User $user, Assignment $assignment): Response
    {
        return $this->reserve(
            $assignment->classroom->isTaughtBy($user),
            fn () => $this->canView($user, $assignment),
            self::OWNER_ONLY_MESSAGE,
        );
    }

    public function viewSolution(User $user, Assignment $assignment): bool
    {
        if ($assignment->classroom->isTaughtBy($user)) {
            return true;
        }

        return $assignment->isPublished()
            && $assignment->solutionReleased()
            && $assignment->classroom->hasMember($user);
    }

    /**
     * Observer en direct : le prof du devoir, et seulement s'il a ouvert le
     * suivi. Le drapeau baissé est un refus explicite, pas un 404 : le prof
     * sait très bien que son devoir existe.
     */
    public function observeLive(User $user, Assignment $assignment): Response
    {
        if ($assignment->classroom->isTaughtBy($user)) {
            return $this->allowOrDeny($assignment->liveTrackingEnabled(), self::TRACKING_OFF_MESSAGE);
        }

        return $this->denyOrHide($this->canView($user, $assignment), self::OWNER_ONLY_MESSAGE);
    }

    public function copyBase(User $user, Assignment $assignment): Response
    {
        return $this->view($user, $assignment);
    }

    /**
     * Voir le devoir : son prof toujours, un membre de la classe seulement
     * s'il est publié. Un brouillon n'existe pas, pour un élève.
     */
    private function canView(User $user, Assignment $assignment): bool
    {
        if ($assignment->classroom->isTaughtBy($user)) {
            return true;
        }

        return $assignment->isPublished() && $assignment->classroom->hasMember($user);
    }
}
