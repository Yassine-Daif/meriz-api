<?php

namespace App\Actions\Documents;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crée un document pour l'utilisateur authentifié, dans la limite de son quota.
 */
class CreateDocument
{
    public const QUOTA_MESSAGE = 'Nombre maximal de documents atteint.';

    /**
     * @param  array{name: string, content: string}  $data
     *
     * @throws ValidationException
     */
    public function handle(User $owner, array $data, ?string $assignmentId = null): Document
    {
        return DB::transaction(function () use ($owner, $data, $assignmentId) {
            // Verrou sur le compte : deux créations simultanées ne dépassent pas le quota.
            User::whereKey($owner->id)->lockForUpdate()->first();

            if ($owner->documents()->count() >= config('documents.max_per_user')) {
                throw ValidationException::withMessages(['content' => self::QUOTA_MESSAGE]);
            }

            // Le propriétaire vient de la relation, jamais des données du client.
            $document = $owner->documents()->make([
                'name' => $data['name'],
                'content' => $data['content'],
            ]);

            // Le rattachement à un devoir vient de l'action de copie.
            $document->forceFill(['assignment_id' => $assignmentId])->save();

            return $document;
        });
    }
}
