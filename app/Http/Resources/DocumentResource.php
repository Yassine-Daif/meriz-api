<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Forme publique d'un document. content n'apparaît que s'il a été chargé :
 * la liste ne le sélectionne pas. Le propriétaire n'est jamais exposé.
 *
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // Rempli quand le document vient de la base d'un devoir.
            'assignment_id' => $this->assignment_id,
            'content' => $this->whenHas('content'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
