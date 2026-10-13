<?php

namespace App\Services\Analytics;

use App\Models\SocialAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * YouTube Data API v3 videos.list (statistics, 50 ids per call) plus YouTube
 * Analytics reports.query for watch time (estimatedMinutesWatched -> seconds),
 * averageViewDuration (seconds) and shares.
 */
class YouTubeFetcher extends PlatformFetcher
{
    public function platform(): string
    {
        return 'youtube';
    }

    public function fetchMetrics(SocialAccount $account, string $token, array $distributions): array
    {
        $out = [];

        foreach (array_chunk($distributions, 50) as $chunk) {
            $byVideo = [];
            foreach ($chunk as $d) {
                $byVideo[(string) $d->external_id] = $d;
            }
            $ids = array_map('strval', array_keys($byVideo));

            $response = Http::withToken($token)->timeout(self::TIMEOUT)
                ->get('https://www.googleapis.com/youtube/v3/videos', [
                    'part' => 'statistics',
                    'id' => implode(',', $ids),
                    'maxResults' => 50,
                ]);
            $this->throwIfQuota($response);
            if ($response->failed()) {
                throw new RuntimeException('YouTube videos.list failed: '.$response->status());
            }

            $stats = [];
            foreach ((array) $response->json('items', []) as $item) {
                $s = $item['statistics'] ?? [];
                $stats[(string) ($item['id'] ?? '')] = [
                    'views' => $s['viewCount'] ?? 0,
                    'likes' => $s['likeCount'] ?? 0,
                    'comments' => $s['commentCount'] ?? 0,
                ];
            }

            $analytics = $this->analytics($token, $chunk, $ids);

            foreach ($byVideo as $videoId => $d) {
                $videoId = (string) $videoId;
                if (! isset($stats[$videoId]) && ! isset($analytics[$videoId])) {
                    continue; // deleted or not visible: keep the last snapshot
                }
                $out[$d->id] = $this->metrics(($stats[$videoId] ?? []) + ($analytics[$videoId] ?? []));
            }
        }

        return $out;
    }

    public function fetchFollowers(SocialAccount $account, string $token): ?int
    {
        $response = Http::withToken($token)->timeout(self::TIMEOUT)
            ->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'statistics', 'mine' => 'true']);
        $this->throwIfQuota($response);
        if ($response->failed()) {
            throw new RuntimeException('YouTube channels.list failed: '.$response->status());
        }

        $count = $response->json('items.0.statistics.subscriberCount');

        return $count === null ? null : (int) $count;
    }

    /**
     * Lifetime watch time per video. A failure here (e.g. missing
     * yt-analytics.readonly scope) only loses watch time, not the counters.
     *
     * @return array<string, array<string, float|int>>
     */
    private function analytics(string $token, array $chunk, array $ids): array
    {
        $earliest = collect($chunk)->map(fn ($d) => $d->published_at ?? $d->created_at)->filter()->min();
        $startDate = $earliest ? $earliest->copy()->utc()->subDay()->toDateString() : '2005-04-23';

        $response = Http::withToken($token)->timeout(self::TIMEOUT)
            ->get('https://youtubeanalytics.googleapis.com/v2/reports', [
                'ids' => 'channel==MINE',
                'startDate' => $startDate,
                'endDate' => now('UTC')->toDateString(),
                'metrics' => 'estimatedMinutesWatched,averageViewDuration,shares',
                'dimensions' => 'video',
                'filters' => 'video=='.implode(',', $ids),
                'maxResults' => 50,
                'sort' => '-estimatedMinutesWatched',
            ]);
        $this->throwIfQuota($response);
        if ($response->failed()) {
            Log::warning('analytics.youtube.reports_failed', ['status' => $response->status()]);

            return [];
        }

        $columns = array_map(fn ($h) => $h['name'] ?? '', (array) $response->json('columnHeaders', []));
        $out = [];
        foreach ((array) $response->json('rows', []) as $row) {
            if (count($row) !== count($columns)) {
                continue;
            }
            $r = array_combine($columns, $row);
            if (! isset($r['video'])) {
                continue;
            }
            $out[(string) $r['video']] = [
                'watch_time_seconds' => (float) ($r['estimatedMinutesWatched'] ?? 0) * 60,
                'avg_view_duration_seconds' => $r['averageViewDuration'] ?? 0,
                'shares' => $r['shares'] ?? 0,
            ];
        }

        return $out;
    }

    private function throwIfQuota(Response $response): void
    {
        $reasons = collect((array) $response->json('error.errors', []))->pluck('reason')->all();
        $limited = ['quotaExceeded', 'rateLimitExceeded', 'userRateLimitExceeded', 'dailyLimitExceeded'];

        if ($response->status() === 429 || array_intersect($reasons, $limited)) {
            throw new RateLimitedException('YouTube quota: '.implode(',', $reasons ?: [$response->status()]));
        }
    }
}
