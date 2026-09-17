<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public const NOT_ACADEMIC_MESSAGE = 'Le mode prof est réservé aux adresses email scolaires ou universitaires.';

    /**
     * Seul un compte à email scolaire ou universitaire peut devenir prof.
     *
     * TODO : quand la vérification d'email existera, exiger aussi
     * $user->hasVerifiedEmail(). Sans elle, n'importe qui peut revendiquer
     * une adresse scolaire qu'il ne possède pas.
     */
    public function becomeTeacher(User $user): Response
    {
        if (! $user->is_academic) {
            return Response::deny(self::NOT_ACADEMIC_MESSAGE);
        }

        return Response::allow();
    }
}
