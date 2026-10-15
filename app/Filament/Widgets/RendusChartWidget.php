<?php

namespace App\Filament\Widgets;

use App\Models\Submission;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Les rendus déposés, semaine par semaine, sur deux mois.
 *
 * Même méthode que les inscriptions : des comptages bornés par des dates,
 * portables d'une base à l'autre.
 */
class RendusChartWidget extends ChartWidget
{
    protected ?string $heading = 'Rendus déposés par semaine';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $labels = [];
        $valeurs = [];
        $debut = Carbon::now()->startOfWeek()->subWeeks(7);

        for ($i = 0; $i < 8; $i++) {
            $semaine = $debut->copy()->addWeeks($i);

            $labels[] = 'sem. '.$semaine->translatedFormat('d/m');
            $valeurs[] = Submission::whereBetween('submitted_at', [
                $semaine->copy()->startOfWeek(),
                $semaine->copy()->endOfWeek(),
            ])->count();
        }

        return [
            'datasets' => [[
                'label' => 'Rendus',
                'data' => $valeurs,
                'borderColor' => 'rgb(16, 185, 129)',
                'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                'fill' => true,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
