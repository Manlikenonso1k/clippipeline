<?php

namespace App\Filament\Widgets;

use App\Models\PostAnalytic;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        
        // Get all time views
        $totalViews = PostAnalytic::whereIn(
            'post_id',
            DB::table('posts')->where('user_id', $user->id)->pluck('id')
        )->sum('views');

        // Get 24-hour views
        $views24h = PostAnalytic::whereIn(
            'post_id',
            DB::table('posts')->where('user_id', $user->id)->pluck('id')
        )->where('recorded_at', '>=', now()->subHours(24))
            ->sum('views');

        // Get 48-hour views
        $views48h = PostAnalytic::whereIn(
            'post_id',
            DB::table('posts')->where('user_id', $user->id)->pluck('id')
        )->where('recorded_at', '>=', now()->subHours(48))
            ->sum('views');

        // Calculate growth velocity
        $growthVelocity24h = $views24h > 0 ? round(($views24h / max($views48h - $views24h, 1)) * 100, 2) : 0;

        // Get total engagements
        $totalEngagement = PostAnalytic::whereIn(
            'post_id',
            DB::table('posts')->where('user_id', $user->id)->pluck('id')
        )->selectRaw('SUM(views + likes + comments) as total')
            ->value('total') ?? 0;

        // Get engagement 24h
        $engagement24h = PostAnalytic::whereIn(
            'post_id',
            DB::table('posts')->where('user_id', $user->id)->pluck('id')
        )->where('recorded_at', '>=', now()->subHours(24))
            ->selectRaw('SUM(views + likes + comments) as total')
            ->value('total') ?? 0;

        return [
            Stat::make('Total Views', number_format($totalViews))
                ->description('All-time across all platforms')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            
            Stat::make('24h Growth Velocity', round($growthVelocity24h, 1) . '%')
                ->description('Growth rate vs previous 24h')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($growthVelocity24h > 0 ? 'success' : 'danger'),

            Stat::make('24h Engagement', number_format($engagement24h))
                ->description('Views + Likes + Comments')
                ->descriptionIcon('heroicon-m-heart')
                ->color('info'),
        ];
    }
}
