<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use App\Policies\Concerns\DecidesAccess;
use Illuminate\Auth\Access\Response;

class LessonPolicy
{
    use DecidesAccess;

    public const OWNER_ONLY_MESSAGE = 'Réservé au prof de la classe.';

    public function create(User $user, Classroom $classroom): Response
    {
        return $this->reserve(
            $classroom->isTaughtBy($user),
            fn () => $classroom->hasMember($user),
            self::OWNER_ONLY_MESSAGE,
        );
    }

    public function view(User $user, Lesson $lesson): Response
    {
        return $this->allowOrHide($this->canView($user, $lesson));
    }

    public function manage(User $user, Lesson $lesson): Response
    {
        return $this->reserve(
            $lesson->classroom->isTaughtBy($user),
            fn () => $this->canView($user, $lesson),
            self::OWNER_ONLY_MESSAGE,
        );
    }

    /**
     * Voir le cours : son prof toujours, un membre de la classe seulement
     * s'il est publié.
     */
    private function canView(User $user, Lesson $lesson): bool
    {
        if ($lesson->classroom->isTaughtBy($user)) {
            return true;
        }

        return $lesson->isPublished() && $lesson->classroom->hasMember($user);
    }
}
