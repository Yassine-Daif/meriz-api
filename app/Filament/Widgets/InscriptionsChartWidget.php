<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Les nouvelles inscriptions, mois par mois, sur un an.
 *
 * Douze comptages bornés par des dates, plutôt qu'un GROUP BY sur une
 * fonction de date : celles-ci s'écrivent différemment en SQLite, en MySQL
 * et en PostgreSQL, et le serveur change de base entre développement et
 * production. Douze petits comptages indexés, c'est portable et assez
 * rapide pour un tableau de bord.
 */
class InscriptionsChartWidget extends ChartWidget
{
    protected static ?int $sort = 10;

    protected ?string $heading = 'Nouvelles inscriptions par mois';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $labels = [];
        $valeurs = [];
        $debut = Carbon::now()->startOfMonth()->subMonths(11);

        for ($i = 0; $i < 12; $i++) {
            $mois = $debut->copy()->addMonths($i);

            $labels[] = $mois->translatedFormat('M Y');
            $valeurs[] = User::whereBetween('created_at', [
                $mois->copy()->startOfMonth(),
                $mois->copy()->endOfMonth(),
            ])->count();
        }

        return [
            'datasets' => [[
                'label' => 'Comptes créés',
                'data' => $valeurs,
                'backgroundColor' => 'rgba(245, 158, 11, 0.5)',
                'borderColor' => 'rgb(245, 158, 11)',
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
