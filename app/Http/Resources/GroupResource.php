<?php

namespace App\Http\Resources;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un groupe, vu par son créateur ou par un membre.
 *
 * Le code n'est donné qu'au créateur. Les personnes sont rendues en profil
 * public : jamais d'email de connexion.
 *
 * @mixin Group
 */
class GroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAdmin = $this->isAdministeredBy($request->user());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'my_role' => $isAdmin ? 'admin' : 'member',
            'join_code' => $this->when($isAdmin, fn () => $this->join_code),
            'members_count' => $this->whenCounted('members'),
            'documents_count' => $this->whenCounted('documents'),
            'creator' => new PublicProfileResource($this->whenLoaded('creator')),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(
                fn (User $member) => [
                    ...(new PublicProfileResource($member))->toArray($request),
                    'is_admin' => $this->creator_id === $member->id,
                    'joined_at' => $member->membership->created_at?->toIso8601String(),
                ],
            )->values()),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
