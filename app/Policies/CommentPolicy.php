<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Accès aux commentaires d'un travail.
 *
 * Qui peut commenter le document peut tout voir et tout classer : la
 * correction va dans les deux sens. La suppression, elle, est réservée à
 * l'auteur du commentaire, au prof du devoir, ou au créateur du groupe pour
 * un document partagé.
 */
class CommentPolicy
{
    public const DELETE_MESSAGE = 'Seul l\'auteur du commentaire ou le responsable du travail peut le supprimer.';

    public function viewAny(User $user, Document $document): Response
    {
        return $this->discussing($user, $document);
    }

    public function create(User $user, Document $document): Response
    {
        return $this->discussing($user, $document);
    }

    public function view(User $user, Comment $comment): Response
    {
        return $this->discussing($user, $comment->document);
    }

    /**
     * Marquer résolu, ou rouvrir : les deux participants le peuvent, c'est
     * une trace de travail commune.
     */
    public function resolve(User $user, Comment $comment): Response
    {
        return $this->discussing($user, $comment->document);
    }

    public function delete(User $user, Comment $comment): Response
    {
        $document = $comment->document;

        // Hors du travail : on ne révèle pas que le commentaire existe.
        if (Gate::forUser($user)->denies('discuss', $document)) {
            return Response::denyAsNotFound();
        }

        if ($comment->user_id === $user->id) {
            return Response::allow();
        }

        $assignment = $document->assignment;

        if ($assignment !== null && Gate::forUser($user)->allows('manage', $assignment)) {
            return Response::allow();
        }

        if ($document->group?->isAdministeredBy($user)) {
            return Response::allow();
        }

        // Il a accès au travail, mais ce commentaire n'est pas le sien.
        return Response::deny(self::DELETE_MESSAGE);
    }

    private function discussing(User $user, Document $document): Response
    {
        return Gate::forUser($user)->allows('discuss', $document)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
