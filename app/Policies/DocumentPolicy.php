<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Un utilisateur n'accède qu'à ses propres documents.
 *
 * Le refus prend la forme d'un 404 : on ne révèle pas qu'un document
 * appartenant à quelqu'un d'autre existe.
 */
class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Document $document): Response
    {
        return $this->owns($user, $document);
    }

    public function update(User $user, Document $document): Response
    {
        return $this->owns($user, $document);
    }

    public function delete(User $user, Document $document): Response
    {
        return $this->owns($user, $document);
    }

    /**
     * Rejoindre la session de co-édition d'un document.
     *
     * Cette règle n'invente rien : elle compose celles qui existent déjà.
     * Elle ouvre donc au seul couple élève propriétaire et prof du devoir
     * lié, ce dernier uniquement quand le suivi en direct est activé.
     *
     * Un document personnel n'a pas de devoir : aucun prof n'y entre. Et la
     * règle part du devoir *de ce document*, donc un autre devoir ne donne
     * jamais accès.
     */
    public function collaborate(User $user, Document $document): bool
    {
        // 1. L'élève, sur son propre travail.
        if ($document->user_id === $user->id) {
            return true;
        }

        // 2. Le prof du devoir lié, suivi activé : AssignmentPolicy::observeLive,
        //    la règle d'observation déjà en place et déjà testée.
        $assignment = $document->assignment;

        if ($assignment !== null && Gate::forUser($user)->allows('observeLive', $assignment)) {
            return true;
        }

        // 3. Point d'extension : le travail de groupe ajoutera ses membres ici.
        return false;
    }

    private function owns(User $user, Document $document): Response
    {
        return $document->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
