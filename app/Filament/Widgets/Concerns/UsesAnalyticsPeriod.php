<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\User;
use App\Services\AnalyticsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Dashboard / Insights widgets read the period (7d/30d/90d) and platform from the page filter form. */
trait UsesAnalyticsPeriod
{
    use InteractsWithPageFilters;

    protected function period(): string
    {
        $period = (string) ($this->pageFilters['period'] ?? '7d');

        return array_key_exists($period, AnalyticsService::PERIODS) ? $period : '7d';
    }

    /** Platform filter (Insights page); 'all' when absent or unknown. */
    protected function platform(): string
    {
        $platform = (string) ($this->pageFilters['platform'] ?? 'all');

        return in_array($platform, AnalyticsService::PLATFORMS, true) ? $platform : 'all';
    }

    protected function analytics(): AnalyticsService
    {
        return app(AnalyticsService::class);
    }

    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    /** "+20.0%" / "-5.0%" / null when there is no previous value. */
    protected static function formatChange(int|float|null $change): ?string
    {
        if ($change === null) {
            return null;
        }

        return ($change >= 0 ? '+' : '').number_format((float) $change, 1).'%';
    }
}
