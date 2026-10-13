<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesAnalyticsPeriod;
use Filament\Widgets\ChartWidget;

/** Daily points for the period (AnalyticsService::timeseries, §5.3). */
class ViewsTrendChart extends ChartWidget
{
    use UsesAnalyticsPeriod;

    protected ?string $heading = 'Daily views & engagement';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public ?string $filter = 'all';

    protected function getFilters(): ?array
    {
        return [
            'all' => 'All platforms',
            'youtube' => 'YouTube',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
        ];
    }

    /** The exact array GET /api/analytics/timeseries returns for this user, period and platform. */
    public function timeseries(): array
    {
        return $this->analytics()->timeseries($this->user(), $this->period(), (string) ($this->filter ?: 'all'));
    }

    protected function getData(): array
    {
        $points = $this->timeseries()['points'];

        return [
            'datasets' => [
                [
                    'label' => 'Views',
                    'data' => array_column($points, 'views'),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Likes + comments + shares',
                    'data' => array_map(fn (array $p) => $p['likes'] + $p['comments'] + $p['shares'], $points),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => array_column($points, 'date'),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
