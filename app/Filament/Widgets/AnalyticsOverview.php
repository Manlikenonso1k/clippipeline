<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesAnalyticsPeriod;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Totals for the period with change vs the previous period (AnalyticsService::overview, §5.1). */
class AnalyticsOverview extends StatsOverviewWidget
{
    use UsesAnalyticsPeriod;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /** The exact array GET /api/analytics/overview returns for this user and period. */
    public function overview(): array
    {
        return $this->analytics()->overview($this->user(), $this->period());
    }

    protected function getHeading(): ?string
    {
        return 'Performance';
    }

    protected function getDescription(): ?string
    {
        $o = $this->overview();

        return "{$o['start']} to {$o['end']} (UTC), compared with the previous {$o['period']}.";
    }

    protected function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getStats(): array
    {
        $o = $this->overview();
        $trend = array_column($o['trend'], 'views');

        return [
            $this->metricStat('Views', $o, 'views')->chart($trend),
            $this->metricStat('Likes', $o, 'likes'),
            $this->metricStat('Comments', $o, 'comments'),
            $this->metricStat('Shares', $o, 'shares'),
            $this->metricStat('Saves', $o, 'saves'),
            $this->metricStat('Watch time', $o, 'watch_time_seconds', fn (int $s): string => self::formatDuration($s)),
            Stat::make('Followers', number_format($o['followers']['total']))
                ->description(($o['followers']['change'] >= 0 ? '+' : '').number_format($o['followers']['change']).' in period')
                ->color($o['followers']['change'] > 0 ? 'success' : ($o['followers']['change'] < 0 ? 'danger' : 'gray')),
            Stat::make('Posts published', number_format($o['posts_published']))
                ->description(number_format($o['connected_accounts']).' active account(s)'),
        ];
    }

    private function metricStat(string $label, array $o, string $key, ?callable $format = null): Stat
    {
        $value = (int) $o['totals'][$key];
        $change = $o['change'][$key];
        $formatted = self::formatChange($change);

        return Stat::make($label, $format ? $format($value) : number_format($value))
            ->description($formatted ? $formatted.' vs previous' : 'No previous data')
            ->descriptionIcon($change === null ? null : ($change >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'))
            ->color($change === null || $change == 0 ? 'gray' : ($change > 0 ? 'success' : 'danger'));
    }

    private static function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
    }
}
