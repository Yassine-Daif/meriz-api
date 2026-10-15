<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ManageRecords;

/**
 * Aucune création de compte depuis le panneau : un compte naît d'une
 * inscription, qui vérifie son adresse email.
 */
class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
