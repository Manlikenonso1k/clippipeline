<?php

namespace App\Services\Analytics;

use App\Models\PostDistribution;
use App\Models\User;
use App\Services\AnalyticsService;

/**
 * CSV export (CONTRACT §14): one row per distribution that gained metrics in the
 * period or was published in it. `views…watch_time_seconds` are the gains in the
 * window (AnalyticsService window math, so the columns sum to the Insights totals);
 * `lifetime_*` are the latest snapshot's counters.
 */
class ExportService
{
    public const COLUMNS = [
        'post_id', 'title', 'platform', 'url', 'published_at',
        'views', 'likes', 'comments', 'shares', 'saves', 'watch_time_seconds', 'captured_on',
        'lifetime_views', 'lifetime_likes', 'lifetime_comments', 'lifetime_shares', 'lifetime_saves', 'lifetime_watch_time_seconds',
    ];

    public function __construct(private AnalyticsService $analytics) {}

    public static function filename(string $period): string
    {
        $period = array_key_exists($period, AnalyticsService::PERIODS) ? $period : '7d';

        return "clippipeline-analytics-{$period}.csv";
    }

    public function csv(User $user, string $period): string
    {
        [, , $start, $end] = $this->analytics->window($period);

        $gains = $this->analytics->periodGainsByDistribution($user, $period);
        $gained = array_keys(array_filter($gains, fn (array $m) => array_sum($m) > 0));

        $distributions = PostDistribution::query()
            ->with('post:id,user_id,title')
            ->whereHas('post', fn ($q) => $q->where('user_id', $user->id))
            ->where(fn ($q) => $q
                ->whereIn('id', $gained)
                ->orWhere(fn ($p) => $p
                    ->where('status', PostDistribution::STATUS_PUBLISHED)
                    ->whereBetween('published_at', [$start->startOfDay(), $end->endOfDay()])))
            ->orderBy('published_at')
            ->orderBy('id')
            ->get();

        $latest = $this->analytics->latestSnapshotsFor($distributions->pluck('id')->all());

        $out = fopen('php://temp', 'r+');
        fputcsv($out, self::COLUMNS, escape: '');

        foreach ($distributions as $d) {
            $snap = $latest[$d->id] ?? null;
            $lifetime = $snap ? $this->analytics->metricsFromRow($snap) : $this->analytics->zeroMetrics();
            $metrics = $gains[$d->id] ?? $this->analytics->zeroMetrics();

            fputcsv($out, [
                $d->post_id,
                self::safe((string) ($d->post?->title ?? '')),
                $d->platform,
                $d->externalUrl() ?? '',
                $d->published_at?->toIso8601String() ?? '',
                $metrics['views'],
                $metrics['likes'],
                $metrics['comments'],
                $metrics['shares'],
                $metrics['saves'],
                $metrics['watch_time_seconds'],
                $snap ? substr((string) $snap->captured_on, 0, 10) : '',
                $lifetime['views'],
                $lifetime['likes'],
                $lifetime['comments'],
                $lifetime['shares'],
                $lifetime['saves'],
                $lifetime['watch_time_seconds'],
            ], escape: '');
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** Neutralise spreadsheet formula injection in user text. */
    private static function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
