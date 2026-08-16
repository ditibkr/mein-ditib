<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private int $cacheTtl = 300;

    public function getStats(): array
    {
        return Cache::remember('dashboard.stats', $this->cacheTtl, function () {
            return [
                'members' => $this->memberStats(),
                'users' => $this->userStats(),
                'communications' => $this->communicationStats(),
            ];
        });
    }

    private function memberStats(): array
    {
        $total = Member::count();
        $active = Member::where('status', 'aktiv')->count();
        $newThisMonth = Member::where('created_at', '>=', now()->startOfMonth())->count();
        $newThisYear = Member::where('created_at', '>=', now()->startOfYear())->count();

        $byCategory = Member::select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $byStatus = Member::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $growthData = $this->getMemberGrowthData();

        return compact('total', 'active', 'newThisMonth', 'newThisYear', 'byCategory', 'byStatus', 'growthData');
    }

    private function getMemberGrowthData(): array
    {
        $months = collect(range(11, 0))->map(function ($i) {
            $date = now()->subMonths($i);
            return [
                'month' => $date->format('Y-m'),
                'label' => $date->locale('de')->isoFormat('MMM YY'),
                'count' => Member::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count(),
            ];
        });

        return $months->toArray();
    }

    private function userStats(): array
    {
        return [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
        ];
    }

    private function communicationStats(): array
    {
        return [
            'newsletters_sent' => Newsletter::where('status', 'versendet')->count(),
            'newsletters_draft' => Newsletter::where('status', 'entwurf')->count(),
        ];
    }

    public function clearCache(): void
    {
        Cache::forget('dashboard.stats');
    }
}
