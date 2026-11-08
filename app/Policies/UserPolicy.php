<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public const NOT_ACADEMIC_MESSAGE = 'Utilisez une adresse scolaire ou universitaire confirmée pour activer le mode prof.';

    public const NOT_VERIFIED_MESSAGE = 'Confirme ton adresse email avant d\'activer le mode prof.';

    /**
     * Parcours simplifié : une adresse scolaire confirmée suffit.
     * Les anciennes attestations restent acceptées pour les comptes concernés.
     */
    public function becomeTeacher(User $user): Response
    {
        // Les rôles existants restent valides, y compris ceux attribués par l'admin.
        if ($user->isTeacher()) {
            return Response::allow();
        }

        if (! $user->hasVerifiedEmail()) {
            return Response::deny(self::NOT_VERIFIED_MESSAGE);
        }

        if (! $user->is_academic && $user->teacher_verified_at === null) {
            return Response::deny(self::NOT_ACADEMIC_MESSAGE);
        }

        return Response::allow();
    }
}
