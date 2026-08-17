<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Member;
use Filament\Widgets\ChartWidget;

class MemberCategoryChart extends ChartWidget
{
    protected static ?string $heading = 'Mitglieder nach Kategorie';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $data = Member::active()
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        $labels = [
            'vollmitglied' => 'Vollmitglied',
            'foerdermitglied' => 'Fördermitglied',
            'ehrenmitglied' => 'Ehrenmitglied',
            'jugend' => 'Jugend',
        ];

        return [
            'datasets' => [
                [
                    'data' => $data->values()->toArray(),
                    'backgroundColor' => [
                        'rgb(16, 185, 129)',
                        'rgb(59, 130, 246)',
                        'rgb(245, 158, 11)',
                        'rgb(99, 102, 241)',
                    ],
                ],
            ],
            'labels' => $data->keys()->map(fn ($k) => $labels[$k] ?? $k)->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
