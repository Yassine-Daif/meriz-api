<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * L'instantané du travail d'un élève, en lecture seule.
 *
 * Le contenu sort tel qu'il est stocké, octet pour octet. Ni note, ni
 * corrigé, ni email : seulement le travail du moment.
 *
 * @mixin Document
 */
class LiveSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'document_id' => $this->id,
            'student' => new PublicProfileResource($this->whenLoaded('user')),
            'name' => $this->name,
            'content' => $this->content,
            // Dernière modification par l'élève : l'observation ne la touche pas.
            'updated_at' => $this->updated_at?->toIso8601String(),
            'observed_at' => $this->last_observed_at?->toIso8601String(),
        ];
    }
}
