<?php

namespace App\Filament\Resources\Classrooms\Pages;

use App\Filament\Resources\Classrooms\ClassroomResource;
use Filament\Resources\Pages\ManageRecords;

/**
 * Aucune action de création : une classe naît d'un prof, dans l'application.
 * Le panneau observe, corrige et supprime.
 */
class ManageClassrooms extends ManageRecords
{
    protected static string $resource = ClassroomResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
