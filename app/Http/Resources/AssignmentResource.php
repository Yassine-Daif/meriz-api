<?php

namespace App\Http\Resources;

use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un devoir, vu par le prof de la classe ou par un membre.
 *
 * Liste blanche. Le corrigé ne sort que si la Policy viewSolution l'autorise :
 * pour un élève, seulement une fois libéré. La consigne et la base ne sortent
 * que dans la vue détaillée.
 *
 * @mixin Assignment
 */
class AssignmentResource extends JsonResource
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
        $user = $request->user();
        $isOwner = $this->classroom->isTaughtBy($user);

        return [
            'id' => $this->id,
            'classroom_id' => $this->classroom_id,
            'title' => $this->title,
            'type' => $this->type->value,
            'due_at' => $this->due_at?->toIso8601String(),
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'has_image' => $this->hasImage(),
            'image_url' => $this->when($this->hasImage(), fn () => url("/api/assignments/{$this->id}/image")),
            'has_base' => $this->hasBase(),
            'has_solution' => $this->when($isOwner, fn () => $this->hasSolution()),
            'solution_released' => $this->solutionReleased(),
            'instructions' => $this->when($this->detailed, fn () => $this->instructions),
            'base_content' => $this->when($this->detailed, fn () => $this->base_content),
            'solution_content' => $this->when(
                $this->detailed && $user->can('viewSolution', $this->resource),
                fn () => $this->solution_content,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
