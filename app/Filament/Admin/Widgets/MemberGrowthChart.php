<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Member;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class MemberGrowthChart extends ChartWidget
{
    protected static ?string $heading = 'Mitgliederwachstum (12 Monate)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $data = collect(range(11, 0))->map(function ($i) {
            $date = now()->subMonths($i);
            return [
                'label' => $date->locale('de')->isoFormat('MMM YY'),
                'count' => Member::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count(),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Neue Mitglieder',
                    'data' => $data->pluck('count')->toArray(),
                    'fill' => true,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $data->pluck('label')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
