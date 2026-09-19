<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mon propre compte, renvoyé seulement à son titulaire. C'est la seule
 * Resource qui contient l'email de connexion. Pour autrui, voir
 * PublicProfileResource. Liste blanche : aucun champ sensible.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'email' => $this->email,
            'role' => $this->role->value,
            'is_academic' => $this->is_academic,
            'bio' => $this->bio,
            'bio_shared' => $this->bio_shared,
            'contact' => $this->contact,
            'contact_shared' => $this->contact_shared,
            'avatar_bg' => $this->avatarBackground(),
            'avatar_fg' => $this->avatarText(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
