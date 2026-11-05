<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Group;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DocumentStorageBudget
{
    public const MESSAGE = 'Espace de stockage des documents atteint. Réduisez un document ou supprimez des documents inutilisés.';

    /** À appeler sous le verrou du compte ou du groupe propriétaire. */
    public function check(User|Group $owner, string $content, ?Document $existing = null): void
    {
        $delta = strlen($content) - ($existing?->content_bytes ?? 0);
        // Un compte au-dessus d'un nouveau plafond peut toujours réduire ses données.
        if ($delta <= 0) {
            return;
        }
        $documents = $owner instanceof Group ? $owner->documents() : $owner->documents()->personal();
        $limit = config($owner instanceof Group ? 'documents.max_group_storage_bytes' : 'documents.max_user_storage_bytes');
        if ((int) $documents->sum('content_bytes') + $delta > $limit) {
            throw ValidationException::withMessages(['content' => self::MESSAGE]);
        }
    }
}
