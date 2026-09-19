<?php

namespace App\Actions\Assignments;

use App\Actions\Documents\CreateDocument;
use App\Models\Assignment;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Donne à l'utilisateur une copie modifiable de la base du devoir, sous la
 * forme d'un nouveau document dans ses documents cloud.
 */
class CopyAssignmentBase
{
    public const NO_BASE_MESSAGE = 'Ce devoir n\'a pas de base. Commencez sur une page blanche.';

    public function __construct(private readonly CreateDocument $createDocument) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user, Assignment $assignment): Document
    {
        if (! $assignment->hasBase()) {
            throw ValidationException::withMessages(['base_content' => self::NO_BASE_MESSAGE]);
        }

        // Seule la base est copiée. Le corrigé n'est jamais lu ici.
        return $this->createDocument->handle($user, [
            'name' => Str::limit($assignment->title, 255, ''),
            'content' => $assignment->base_content,
        ]);
    }
}
