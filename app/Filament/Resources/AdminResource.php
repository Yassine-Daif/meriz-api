<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

/**
 * Base de toutes les ressources du panneau.
 *
 * **Le panneau ne consulte pas les Policies de l'API, exprès.** Elles
 * répondent à une autre question : « ce prof peut-il voir cette classe ? »,
 * « cet élève peut-il commenter ce travail ? ». Plusieurs ont donc une
 * signature qui attend la ressource parente, comme
 * `CommentPolicy::viewAny(User, Document)`, et Filament les appelle sans
 * elle pour construire sa navigation.
 *
 * La question du panneau est différente, et elle est déjà tranchée :
 * `User::canAccessPanel()` n'y laisse entrer qu'un administrateur. Une fois
 * dedans, il n'y a qu'une seule classe d'utilisateur, et tout lui est ouvert.
 * C'est pour ça que l'autorisation par Policy est désactivée ici, et c'est
 * la seule raison.
 *
 * Rien ne se crée depuis le panneau : les données naissent dans
 * l'application, par l'API, avec ses règles.
 */
abstract class AdminResource extends Resource
{
    protected static bool $shouldSkipAuthorization = true;

    public static function canCreate(): bool
    {
        return false;
    }
}
