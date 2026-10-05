<?php

namespace App\Actions\Groups;

use App\Models\Document;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crée un document dans l'espace commun d'un groupe.
 *
 * Le groupe et l'auteur sont écrits par le serveur. Le quota est celui du
 * groupe : un document partagé ne mange pas le quota personnel des membres.
 */
class CreateGroupDocument
{
    public const QUOTA_MESSAGE = 'Nombre maximal de documents atteint pour ce groupe.';

    /**
     * @param  array{name: string, content: string}  $data
     *
     * @throws ValidationException
     */
    public function handle(Group $group, User $author, array $data): Document
    {
        return DB::transaction(function () use ($group, $author, $data) {
            Group::whereKey($group->id)->lockForUpdate()->first();

            if ($group->documents()->count() >= config('groups.max_documents_per_group')) {
                throw ValidationException::withMessages(['content' => self::QUOTA_MESSAGE]);
            }

            $document = new Document([
                'name' => $data['name'],
                'content' => $data['content'],
            ]);

            $document->forceFill([
                'user_id' => $author->id,
                'group_id' => $group->id,
            ])->save();

            return $document;
        });
    }
}
