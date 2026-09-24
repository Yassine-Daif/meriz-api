<?php

namespace App\Http\Resources;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un cours, vu par le prof de la classe ou par un membre.
 *
 * Liste blanche. La page de blocs ne sort qu'en vue détaillée : les listes
 * ne la lisent même pas en base.
 *
 * @mixin Lesson
 */
class LessonResource extends JsonResource
{
    private bool $detailed = false;

    public function detailed(): static
    {
        $this->detailed = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'classroom_id' => $this->classroom_id,
            'title' => $this->title,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'media_count' => $this->whenCounted('media'),
            'blocks' => $this->when($this->detailed, fn () => $this->blocks),
            'media' => LessonMediumResource::collection($this->whenLoaded('media')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
