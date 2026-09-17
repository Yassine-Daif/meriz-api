<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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

    private function owns(User $user, Document $document): Response
    {
        return $document->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
