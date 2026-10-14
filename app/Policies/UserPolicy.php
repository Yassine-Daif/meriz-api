<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public const NOT_ACADEMIC_MESSAGE = 'Le mode prof est réservé aux adresses email scolaires ou universitaires.';

    public const NOT_VERIFIED_MESSAGE = 'Confirme ton adresse email avant d\'activer le mode prof.';

    /**
     * Devenir prof demande deux choses : une adresse scolaire ou
     * universitaire, et la preuve qu'on la possède.
     *
     * Sans la vérification, n'importe qui revendiquerait une adresse
     * scolaire qui n'est pas la sienne, et verrait le travail d'élèves.
     */
    public function becomeTeacher(User $user): Response
    {
        if (! $user->is_academic) {
            return Response::deny(self::NOT_ACADEMIC_MESSAGE);
        }

        if (! $user->hasVerifiedEmail()) {
            return Response::deny(self::NOT_VERIFIED_MESSAGE);
        }

        return Response::allow();
    }
}
