<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static ?string $title = 'Vue d’ensemble';

    protected static string|\UnitEnum|null $navigationGroup = 'Pilotage';

    public function getSubheading(): ?string
    {
        return 'La plateforme, l’enseignement et la fréquentation réunis dans votre espace de pilotage.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('traffic')->label('Statistiques de fréquentation')->icon(Heroicon::OutlinedChartBar)->url(Traffic::getUrl()),
        ];
    }
}
