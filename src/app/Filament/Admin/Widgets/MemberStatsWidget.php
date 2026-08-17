<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MemberStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $total = Member::count();
        $active = Member::where('status', 'aktiv')->count();
        $newThisMonth = Member::where('created_at', '>=', now()->startOfMonth())->count();

        return [
            Stat::make('Mitglieder gesamt', $total)
                ->description('Alle registrierten Mitglieder')
                ->icon('heroicon-o-users')
                ->color('primary'),

            Stat::make('Aktive Mitglieder', $active)
                ->description(number_format($total > 0 ? ($active / $total * 100) : 0, 1) . '% aktiv')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Neue diesen Monat', $newThisMonth)
                ->description(now()->locale('de')->isoFormat('MMMM YYYY'))
                ->icon('heroicon-o-user-plus')
                ->color('warning'),
        ];
    }
}
