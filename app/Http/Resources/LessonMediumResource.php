<?php

namespace App\Http\Resources;

use App\Models\LessonMedium;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un média de cours. Le chemin de stockage n'est jamais exposé : seule
 * l'URL de la route protégée l'est.
 *
 * @mixin LessonMedium
 */
class LessonMediumResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'mime' => $this->mime,
            'size' => $this->size,
            'name' => $this->name,
            'url' => url("/api/lessons/{$this->lesson_id}/media/{$this->id}"),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
