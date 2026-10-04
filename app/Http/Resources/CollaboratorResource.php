<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Identité d'un participant à une session de co-édition.
 *
 * Liste blanche réduite au strict nécessaire pour afficher un curseur et une
 * étiquette : nom, prénom, rôle et couleurs. Ni email, ni présentation, ni
 * contact, ni note, ni corrigé. La présence d'un canal est visible de tous
 * ses participants, donc elle ne porte que ce qui doit l'être.
 *
 * @mixin User
 */
class CollaboratorResource extends JsonResource
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
            // Pour l'étiquette « prof » côté application.
            'role' => $this->role->value,
            // Couleur du curseur et de l'étiquette.
            'avatar_bg' => $this->avatarBackground(),
            'avatar_fg' => $this->avatarText(),
        ];
    }
}
