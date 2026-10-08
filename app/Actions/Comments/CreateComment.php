<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pose un commentaire sur un travail.
 *
 * L'auteur vient du jeton, le document de la route : on ne commente jamais
 * au nom d'un autre. La position est écrite telle quelle, le serveur ne la
 * lit pas.
 */
class CreateComment
{
    public const QUOTA_MESSAGE = 'Nombre maximal de commentaires atteint pour ce travail.';

    /**
     * @param  array{body: string, position_x?: float|null, position_y?: float|null}  $data
     *
     * @throws ValidationException
     */
    public function handle(Document $document, User $author, array $data): Comment
    {
        return DB::transaction(function () use ($document, $author, $data) {
            Document::whereKey($document->id)->lockForUpdate()->first();

            if ($document->comments()->count() >= config('comments.max_per_document')) {
                throw ValidationException::withMessages(['body' => self::QUOTA_MESSAGE]);
            }

            $comment = new Comment(['body' => $data['body']]);

            $comment->forceFill([
                'document_id' => $document->id,
                'user_id' => $author->id,
                'position_x' => $data['position_x'] ?? null,
                'position_y' => $data['position_y'] ?? null,
            ])->save();

            return $comment;
        });
    }
}
