<?php

namespace App\Filament\Widgets;

use App\Models\PostAnalytic;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PerformanceChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Performance Over 30 Days';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $user = auth()->user();
        $days = 30;
        $platforms = ['youtube', 'instagram', 'tiktok'];

        $labels = [];
        $datasets = [];

        // Generate date labels
        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = now()->subDays($i)->format('M d');
        }

        // Get data for each platform
        $platformColors = [
            'youtube' => 'rgb(255, 0, 0)',
            'instagram' => 'rgb(255, 64, 129)',
            'tiktok' => 'rgb(0, 0, 0)',
        ];

        foreach ($platforms as $platform) {
            $data = [];
            
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = now()->subDays($i);
                
                $views = PostAnalytic::whereIn(
                    'post_id',
                    DB::table('posts')->where('user_id', $user->id)->pluck('id')
                )
                ->where('platform', $platform)
                ->whereDate('recorded_at', $date->toDateString())
                ->sum('views');

                $data[] = $views;
            }

            $datasets[] = [
                'label' => ucfirst($platform),
                'data' => $data,
                'borderColor' => $platformColors[$platform] ?? 'rgb(75, 192, 192)',
                'backgroundColor' => str_replace('rgb', 'rgba', $platformColors[$platform] ?? 'rgb(75, 192, 192)') . ', 0.1)',
                'tension' => 0.4,
                'fill' => true,
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => true,
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'stacked' => false,
                ],
            ],
        ];
    }
}
