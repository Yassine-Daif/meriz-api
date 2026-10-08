<?php

namespace App\Http\Resources;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un commentaire d'un travail.
 *
 * Liste blanche. Les personnes sont rendues en profil public, donc jamais
 * d'email. La ressource ne lit que la table des commentaires : ni note, ni
 * corrigé ne peuvent en sortir.
 *
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'body' => $this->body,
            // Bulle posée, ou commentaire général si null.
            'position' => $this->hasPosition()
                ? ['x' => $this->position_x, 'y' => $this->position_y]
                : null,
            'resolved' => $this->isResolved(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolver' => new PublicProfileResource($this->whenLoaded('resolver')),
            'author' => new PublicProfileResource($this->whenLoaded('author')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
