<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;
use App\Policies\Concerns\DecidesAccess;
use Illuminate\Auth\Access\Response;

class ClassroomPolicy
{
    use DecidesAccess;

    public const TEACHER_ONLY_MESSAGE = 'Seul un compte prof peut créer une classe.';

    public const OWNER_ONLY_MESSAGE = 'Réservé au prof de la classe.';

    public const OWNER_CANNOT_LEAVE_MESSAGE = 'Le prof ne peut pas quitter sa propre classe.';

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): Response
    {
        return $this->allowOrDeny($user->isTeacher(), self::TEACHER_ONLY_MESSAGE);
    }

    public function view(User $user, Classroom $classroom): Response
    {
        return $this->allowOrHide($classroom->isTaughtBy($user) || $classroom->hasMember($user));
    }

    public function manage(User $user, Classroom $classroom): Response
    {
        return $this->reserve(
            $classroom->isTaughtBy($user),
            fn () => $classroom->hasMember($user),
            self::OWNER_ONLY_MESSAGE,
        );
    }

    /**
     * Quitter une classe : un membre peut, le prof non — il la supprime.
     * Le refus du prof est explicite, il sait que sa classe existe.
     */
    public function leave(User $user, Classroom $classroom): Response
    {
        if ($classroom->isTaughtBy($user)) {
            return Response::deny(self::OWNER_CANNOT_LEAVE_MESSAGE);
        }

        return $this->allowOrHide($classroom->hasMember($user));
    }
}
