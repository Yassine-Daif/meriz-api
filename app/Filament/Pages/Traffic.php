<?php

namespace App\Filament\Pages;

use App\Services\TrafficReport;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Traffic extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $title = 'Fréquentation';

    protected static string|\UnitEnum|null $navigationGroup = 'Pilotage';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.traffic';

    public string $source = 'site';

    public int $days = 7;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true && ! auth()->user()->isAnonymized();
    }

    public function report(): ?array
    {
        return app(TrafficReport::class)->read($this->source, $this->days);
    }
}
