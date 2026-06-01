<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ViewsTrendChart extends ChartWidget
{
    protected ?string $heading = 'Views (last 14 days)';
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $userId = Auth::id();

        if (! $userId) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $start = now()->subDays(13)->startOfDay();

        $rows = DB::table('post_analytics')
            ->join('posts', 'posts.id', '=', 'post_analytics.post_id')
            ->where('posts.user_id', $userId)
            ->whereDate('post_analytics.recorded_at', '>=', $start->toDateString())
            ->selectRaw('DATE(post_analytics.recorded_at) as date, SUM(views) as views')
            ->groupBy(DB::raw('DATE(post_analytics.recorded_at)'))
            ->orderBy(DB::raw('DATE(post_analytics.recorded_at)'))
            ->get();

        $viewsByDate = $rows->pluck('views', 'date');
        $labels = [];
        $data = [];

        for ($i = 0; $i < 14; $i++) {
            $day = $start->copy()->addDays($i);
            $labels[] = $day->format('M j');
            $data[] = (int) ($viewsByDate[$day->toDateString()] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Views',
                    'data' => $data,
                    'borderColor' => '#ff6b35',
                    'backgroundColor' => 'rgba(255, 107, 53, 0.2)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
