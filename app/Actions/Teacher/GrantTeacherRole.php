<?php

namespace App\Actions\Teacher;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Donne le rôle prof. Idempotent.
 *
 * C'est le seul endroit qui écrit ce rôle, et le contrôleur l'appelle une
 * fois UserPolicy::becomeTeacher passée. Si une validation par un
 * administrateur arrive un jour, c'est cette action qu'elle appellera au
 * moment de l'approbation.
 */
class GrantTeacherRole
{
    public function handle(User $user): User
    {
        if (! $user->isTeacher()) {
            $user->forceFill(['role' => UserRole::Teacher])->save();
        }

        return $user;
    }
}
