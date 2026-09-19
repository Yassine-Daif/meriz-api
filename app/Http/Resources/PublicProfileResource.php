<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ce qu'autrui voit d'un utilisateur : nom, prénom, et les champs de profil
 * que la personne a choisi de partager. Liste blanche : jamais l'email de
 * connexion, ni le rôle, ni les réglages de partage.
 *
 * @mixin User
 */
class PublicProfileResource extends JsonResource
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
            'bio' => $this->sharedBio(),
            'contact' => $this->sharedContact(),
            // Couleurs de la pastille : choisies pour être vues, donc pas
            // d'interrupteur de partage.
            'avatar_bg' => $this->avatarBackground(),
            'avatar_fg' => $this->avatarText(),
        ];
    }
}
