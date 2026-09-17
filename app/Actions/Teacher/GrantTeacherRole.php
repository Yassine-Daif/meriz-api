<?php

namespace App\Actions\Teacher;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Donne le rôle prof. Idempotent.
 *
 * C'est le seul endroit qui écrit ce rôle. Une future validation par un
 * administrateur appellera cette action au moment de l'approbation.
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
