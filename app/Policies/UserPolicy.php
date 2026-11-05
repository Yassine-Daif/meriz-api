<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public const NOT_ACADEMIC_MESSAGE = 'Le statut enseignant doit être confirmé par votre établissement avant d’activer le mode prof.';

    public const NOT_VERIFIED_MESSAGE = 'Confirme ton adresse email avant d\'activer le mode prof.';

    /**
     * Une boîte email confirmée et une attestation du statut enseignant
     * sont deux preuves différentes. Le domaine scolaire ne distingue
     * pas un étudiant d'un professeur.
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

        if ($user->teacher_verified_at === null) {
            return Response::deny(self::NOT_ACADEMIC_MESSAGE);
        }

        return Response::allow();
    }
}
