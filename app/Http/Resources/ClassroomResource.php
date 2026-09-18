<?php

namespace App\Http\Resources;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une classe, vue par son prof ou par un membre.
 *
 * Le prof propriétaire voit en plus le code et la date d'arrivée des
 * membres. Les personnes sont toujours rendues en profil public : jamais
 * d'email de connexion.
 *
 * @mixin Classroom
 */
class ClassroomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isOwner = $this->isTaughtBy($request->user());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'my_role' => $isOwner ? 'teacher' : 'student',
            'join_code' => $this->when($isOwner, fn () => $this->join_code),
            'members_count' => $this->whenCounted('members'),
            'teacher' => new PublicProfileResource($this->whenLoaded('teacher')),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(
                fn (User $member) => [
                    ...(new PublicProfileResource($member))->toArray($request),
                    ...($isOwner ? ['joined_at' => $member->membership->created_at?->toIso8601String()] : []),
                ],
            )->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
