<?php

namespace App\Actions\Teacher;

use App\Enums\TeacherRequestStatus;
use App\Models\User;

/**
 * Traite la demande de passage en mode prof d'un compte déjà autorisé
 * par UserPolicy::becomeTeacher.
 *
 * Pour l'instant, la demande est approuvée tout de suite. Pour brancher
 * une validation par un administrateur, il suffira ici d'enregistrer une
 * demande en attente et de renvoyer un statut Pending. L'administrateur
 * appellera ensuite GrantTeacherRole.
 */
class RequestTeacherRole
{
    public function __construct(private readonly GrantTeacherRole $grant) {}

    public function handle(User $user): TeacherRequestStatus
    {
        $this->grant->handle($user);

        return TeacherRequestStatus::Approved;
    }
}
