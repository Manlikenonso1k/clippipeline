<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $userId = Auth::id();

        if (! $userId) {
            return [
                Stat::make('Total Views', '0'),
                Stat::make('Total Likes', '0'),
                Stat::make('Total Comments', '0'),
            ];
        }

        $totals = DB::table('post_analytics')
            ->join('posts', 'posts.id', '=', 'post_analytics.post_id')
            ->where('posts.user_id', $userId)
            ->selectRaw('COALESCE(SUM(views), 0) as views, COALESCE(SUM(likes), 0) as likes, COALESCE(SUM(comments), 0) as comments')
            ->first();

        return [
            Stat::make('Total Views', number_format((int) ($totals->views ?? 0))),
            Stat::make('Total Likes', number_format((int) ($totals->likes ?? 0))),
            Stat::make('Total Comments', number_format((int) ($totals->comments ?? 0))),
        ];
    }
}
