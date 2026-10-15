<?php

namespace App\Filament\Resources\Lessons\Pages;

use App\Filament\Resources\Lessons\LessonResource;
use Filament\Resources\Pages\ManageRecords;

class ManageLessons extends ManageRecords
{
    protected static string $resource = LessonResource::class;

    protected function getHeaderActions(): array
    {
        // Rien ne se crée depuis le panneau : les données naissent dans
        // l'application.
        return [];
    }
}
