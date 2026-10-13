<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostAnalytic;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The single source of analytics numbers for the API and Filament (docs/CONTRACT.md §5).
 *
 * post_analytics rows are LIFETIME counters, one per distribution per day. Every
 * figure is derived from value(d, day) = latest snapshot of d with captured_on <= day.
 */
class AnalyticsService
{
    public const PLATFORMS = ['youtube', 'facebook', 'instagram', 'tiktok'];

    public const PERIODS = ['7d' => 7, '30d' => 30, '90d' => 90];

    /** §5.1 */
    public function overview(User $user, string $period): array
    {
        [$period, $days, $start, $end] = $this->window($period);
        $prevStart = $start->subDays($days);
        $prevEnd = $start->subDay();

        $series = $this->loadSeries($this->distributionIds($user), $prevStart->subDay(), $end);

        $totals = $this->periodTotals($series, $start, $end);
        $previous = $this->periodTotals($series, $prevStart, $prevEnd);
        $points = $this->dailyPoints($series, $start, $end);

        $accountIds = SocialAccount::query()->where('user_id', $user->id)->pluck('id')->all();

        return [
            'period' => $period,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'totals' => $totals,
            'previous' => $previous,
            'change' => $this->change($totals, $previous),
            'followers' => $this->followers($accountIds, $start, $end),
            'posts_published' => $this->postsPublished($user, $start, $end),
            'connected_accounts' => SocialAccount::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->count(),
            'total_views' => $totals['views'],
            'total_likes' => $totals['likes'],
            'total_comments' => $totals['comments'],
            'total_shares' => $totals['shares'],
            'trend' => array_map(fn (array $p) => ['date' => $p['date'], 'views' => $p['views']], $points),
        ];
    }

    /** §5.2 — always all four platforms, in canonical order. */
    public function platforms(User $user, string $period): array
    {
        [$period, $days, $start, $end] = $this->window($period);
        $prevStart = $start->subDays($days);
        $prevEnd = $start->subDay();

        $accounts = SocialAccount::query()->where('user_id', $user->id)->get(['id', 'provider', 'status']);
        $out = [];

        foreach (self::PLATFORMS as $platform) {
            $series = $this->loadSeries($this->distributionIds($user, $platform), $prevStart->subDay(), $end);
            $totals = $this->periodTotals($series, $start, $end);
            $previous = $this->periodTotals($series, $prevStart, $prevEnd);
            $platformAccounts = $accounts->where('provider', $platform);

            $out[] = [
                'platform' => $platform,
                'connected' => $platformAccounts->contains(fn ($a) => $a->status === 'active'),
                'followers' => $this->followers($platformAccounts->pluck('id')->all(), $start, $end)['total'],
                'posts_published' => $this->postsPublished($user, $start, $end, $platform),
                'totals' => $totals,
                'change' => $this->change($totals, $previous),
            ];
        }

        return ['period' => $period, 'platforms' => $out];
    }

    /** §5.3 — one zero-filled point per day. */
    public function timeseries(User $user, string $period, string $platform = 'all'): array
    {
        [$period, , $start, $end] = $this->window($period);
        $platform = in_array($platform, self::PLATFORMS, true) ? $platform : 'all';

        $series = $this->loadSeries(
            $this->distributionIds($user, $platform === 'all' ? null : $platform),
            $start->subDay(),
            $end,
        );

        return [
            'period' => $period,
            'platform' => $platform,
            'points' => $this->dailyPoints($series, $start, $end),
        ];
    }

    /** §5.4 — lifetime numbers per distribution plus a 30-day daily series. */
    public function post(Post $post): array
    {
        $end = CarbonImmutable::now('UTC')->startOfDay();
        $start = $end->subDays(29);

        $distributions = PostDistribution::query()
            ->where('post_id', $post->id)
            ->get()
            ->sortBy(fn (PostDistribution $d) => [$this->platformRank($d->platform), $d->id])
            ->values();

        $series = $this->loadSeries($distributions->pluck('id')->all(), $start->subDay(), $end);
        $latest = $this->latestSnapshots($distributions->pluck('id')->all());

        $totals = $this->zeroMetrics();
        $platforms = [];

        foreach ($distributions as $distribution) {
            $snap = $latest[$distribution->id] ?? null;
            $metrics = $snap ? $this->metricsOf($snap) : $this->zeroMetrics();
            foreach ($metrics as $k => $v) {
                $totals[$k] += $v;
            }

            $platforms[] = [
                'platform' => $distribution->platform,
                'distribution_id' => $distribution->id,
                'status' => $distribution->status,
                'url' => $distribution->externalUrl(),
                'captured_on' => $snap ? substr((string) $snap->captured_on, 0, 10) : null,
                'metrics' => $metrics,
                'points' => $this->dailyPoints(
                    array_intersect_key($series, [$distribution->id => true]),
                    $start,
                    $end,
                ),
            ];
        }

        return [
            'post_id' => $post->id,
            'totals' => $totals,
            'platforms' => $platforms,
        ];
    }

    /** §5.5 — posts ranked by views gained in the window. */
    public function topPosts(User $user, string $period, int $limit = 5): array
    {
        [, , $start, $end] = $this->window($period);
        $limit = max(1, min(20, $limit));

        $distributions = PostDistribution::query()
            ->whereIn('post_id', Post::query()->select('id')->where('user_id', $user->id))
            ->get(['id', 'post_id', 'platform']);

        $series = $this->loadSeries($distributions->pluck('id')->all(), $start->subDay(), $end);

        $ranked = $distributions->groupBy('post_id')
            ->map(function (Collection $group, $postId) use ($series, $start, $end) {
                $ids = array_flip($group->pluck('id')->all());

                return [
                    'post_id' => (int) $postId,
                    'platforms' => $group->pluck('platform')->unique()
                        ->sortBy(fn ($p) => $this->platformRank($p))->values()->all(),
                    'totals' => $this->periodTotals(array_intersect_key($series, $ids), $start, $end),
                ];
            })
            ->filter(fn (array $row) => array_sum($row['totals']) > 0)
            ->sort(fn (array $a, array $b) => [$b['totals']['views'], $b['post_id']] <=> [$a['totals']['views'], $a['post_id']])
            ->take($limit)
            ->values();

        $posts = Post::query()->whereIn('id', $ranked->pluck('post_id'))->get(['id', 'title', 'thumbnail'])->keyBy('id');

        return $ranked->map(fn (array $row) => [
            'post_id' => $row['post_id'],
            'title' => $posts[$row['post_id']]->title ?? null,
            'thumbnail' => $posts[$row['post_id']]->thumbnail ?? null,
            'platforms' => $row['platforms'],
            'totals' => $row['totals'],
        ])->all();
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return array{0:string,1:int,2:CarbonImmutable,3:CarbonImmutable}
     */
    public function window(string $period): array
    {
        $period = array_key_exists($period, self::PERIODS) ? $period : '7d';
        $days = self::PERIODS[$period];
        $end = CarbonImmutable::now('UTC')->startOfDay();

        return [$period, $days, $end->subDays($days - 1), $end];
    }

    /** @return array<string,int> */
    public function zeroMetrics(): array
    {
        return array_fill_keys(PostAnalytic::METRICS, 0);
    }

    /** @return array<int,int> distribution ids belonging to the user's posts. */
    private function distributionIds(User $user, ?string $platform = null): array
    {
        return PostDistribution::query()
            ->whereIn('post_id', Post::query()->select('id')->where('user_id', $user->id))
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->pluck('id')
            ->all();
    }

    /**
     * Snapshots per distribution needed to evaluate value(d, day) for every day in
     * [$from, $to]: all rows inside the range plus the latest row before it.
     *
     * @param  array<int,int>  $distributionIds
     * @return array<int, array<int, array{date:string, metrics:array<string,int>}>> sorted by date
     */
    private function loadSeries(array $distributionIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($distributionIds === []) {
            return [];
        }

        $series = [];
        $columns = array_merge(['distribution_id', 'captured_on'], PostAnalytic::METRICS);

        foreach (array_chunk($distributionIds, 500) as $chunk) {
            $before = DB::table('post_analytics')
                ->select('distribution_id', DB::raw('MAX(captured_on) as captured_on'))
                ->whereIn('distribution_id', $chunk)
                ->where('captured_on', '<', $from->toDateString())
                ->groupBy('distribution_id');

            $rows = DB::table('post_analytics as pa')
                ->joinSub($before, 'b', fn ($j) => $j->on('b.distribution_id', '=', 'pa.distribution_id')
                    ->on('b.captured_on', '=', 'pa.captured_on'))
                ->select(array_map(fn ($c) => 'pa.'.$c, $columns))
                ->get()
                ->concat(DB::table('post_analytics')
                    ->select($columns)
                    ->whereIn('distribution_id', $chunk)
                    ->whereBetween('captured_on', [$from->toDateString(), $to->toDateString()])
                    ->get());

            foreach ($rows as $row) {
                $series[(int) $row->distribution_id][] = [
                    'date' => substr((string) $row->captured_on, 0, 10),
                    'metrics' => $this->metricsOf($row),
                ];
            }
        }

        foreach ($series as &$snaps) {
            usort($snaps, fn ($a, $b) => strcmp($a['date'], $b['date']));
        }

        return $series;
    }

    /** value(d, day) for one distribution's sorted snapshots. */
    private function valueAt(array $snaps, string $day): array
    {
        $value = $this->zeroMetrics();
        foreach ($snaps as $snap) {
            if ($snap['date'] > $day) {
                break;
            }
            $value = $snap['metrics'];
        }

        return $value;
    }

    /** Σ_d max(0, value(d, end) − value(d, start − 1)) per metric. */
    private function periodTotals(array $series, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $totals = $this->zeroMetrics();
        $before = $start->subDay()->toDateString();
        $last = $end->toDateString();

        foreach ($series as $snaps) {
            $a = $this->valueAt($snaps, $before);
            $b = $this->valueAt($snaps, $last);
            foreach ($totals as $k => $_) {
                $totals[$k] += max(0, $b[$k] - $a[$k]);
            }
        }

        return $totals;
    }

    /** One point per day: Σ_d max(0, value(d, day) − value(d, day − 1)). */
    private function dailyPoints(array $series, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $days = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $days[] = $day->toDateString();
        }

        $points = [];
        foreach ($days as $date) {
            $points[$date] = ['date' => $date] + $this->zeroMetrics();
        }

        $previousDay = $start->subDay()->toDateString();
        foreach ($series as $snaps) {
            $prev = $this->valueAt($snaps, $previousDay);
            foreach ($days as $date) {
                $cur = $this->valueAt($snaps, $date);
                foreach (PostAnalytic::METRICS as $k) {
                    $points[$date][$k] += max(0, $cur[$k] - $prev[$k]);
                }
                $prev = $cur;
            }
        }

        return array_values($points);
    }

    /** round((cur − prev) / prev * 100, 1), null when prev == 0. */
    private function change(array $current, array $previous): array
    {
        $out = [];
        foreach (PostAnalytic::METRICS as $k) {
            $out[$k] = $previous[$k] == 0
                ? null
                : round(($current[$k] - $previous[$k]) / $previous[$k] * 100, 1);
        }

        return $out;
    }

    /**
     * Latest follower snapshot per account ≤ end, summed; change = absolute delta
     * vs the same sum at start − 1.
     *
     * @param  array<int,int>  $accountIds
     * @return array{total:int, change:int}
     */
    private function followers(array $accountIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($accountIds === []) {
            return ['total' => 0, 'change' => 0];
        }

        $sumAt = function (string $day) use ($accountIds): int {
            $latest = DB::table('account_follower_snapshots')
                ->select('social_account_id', DB::raw('MAX(captured_on) as captured_on'))
                ->whereIn('social_account_id', $accountIds)
                ->where('captured_on', '<=', $day)
                ->groupBy('social_account_id');

            return (int) DB::table('account_follower_snapshots as s')
                ->joinSub($latest, 'l', fn ($j) => $j->on('l.social_account_id', '=', 's.social_account_id')
                    ->on('l.captured_on', '=', 's.captured_on'))
                ->sum('s.followers');
        };

        $total = $sumAt($end->toDateString());

        return ['total' => $total, 'change' => $total - $sumAt($start->subDay()->toDateString())];
    }

    /** Distinct posts with a distribution published inside [start, end]. */
    private function postsPublished(User $user, CarbonImmutable $start, CarbonImmutable $end, ?string $platform = null): int
    {
        return PostDistribution::query()
            ->where('status', PostDistribution::STATUS_PUBLISHED)
            ->whereIn('post_id', Post::query()->select('id')->where('user_id', $user->id))
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->whereBetween('published_at', [$start->startOfDay(), $end->endOfDay()])
            ->distinct()
            ->count('post_id');
    }

    /** @param  array<int,int>  $distributionIds */
    private function latestSnapshots(array $distributionIds): array
    {
        if ($distributionIds === []) {
            return [];
        }

        $latest = DB::table('post_analytics')
            ->select('distribution_id', DB::raw('MAX(captured_on) as captured_on'))
            ->whereIn('distribution_id', $distributionIds)
            ->groupBy('distribution_id');

        return DB::table('post_analytics as pa')
            ->joinSub($latest, 'l', fn ($j) => $j->on('l.distribution_id', '=', 'pa.distribution_id')
                ->on('l.captured_on', '=', 'pa.captured_on'))
            ->select('pa.*')
            ->get()
            ->keyBy('distribution_id')
            ->all();
    }

    /** @return array<string,int> */
    private function metricsOf(object $row): array
    {
        $out = [];
        foreach (PostAnalytic::METRICS as $k) {
            $out[$k] = (int) ($row->{$k} ?? 0);
        }

        return $out;
    }

    private function platformRank(?string $platform): int
    {
        $i = array_search($platform, self::PLATFORMS, true);

        return $i === false ? 99 : $i;
    }
}
