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
     * @param  array{body: string, position_x?: float|null, position_y?: float|null, parent_id?: string|null, drawing?: array|null}  $data
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

            if (! empty($data['parent_id'])) {
                $parent = $document->comments()->whereKey($data['parent_id'])
                    ->whereNull('parent_id')->whereNull('drawing')->lockForUpdate()->first();
                if ($parent === null) {
                    throw ValidationException::withMessages(['parent_id' => 'Cette discussion est indisponible.']);
                }
                // Une nouvelle réponse remet la discussion parmi celles à traiter.
                $parent->forceFill(['resolved_at' => null, 'resolved_by' => null])->save();
            }

            $comment = new Comment(['body' => $data['body']]);

            $comment->forceFill([
                'document_id' => $document->id,
                'user_id' => $author->id,
                'position_x' => $data['position_x'] ?? null,
                'position_y' => $data['position_y'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'drawing' => isset($data['drawing']) ? array_map(
                    fn (array $point) => ['x' => (float) $point['x'], 'y' => (float) $point['y']],
                    $data['drawing'],
                ) : null,
            ])->save();

            return $comment;
        });
    }
}
