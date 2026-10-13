<?php

namespace App\Services\Analytics;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Instagram Graph API media insights for Reels (lifetime). Current metric names:
 * views, likes, comments, shares, saved, ig_reels_avg_watch_time and
 * ig_reels_video_view_total_time (both milliseconds). `plays` is deprecated.
 */
class InstagramFetcher extends PlatformFetcher
{
    public const REELS_METRICS = 'views,likes,comments,shares,saved,ig_reels_avg_watch_time,ig_reels_video_view_total_time';

    public const BASIC_METRICS = 'views,likes,comments,shares,saved';

    public function platform(): string
    {
        return 'instagram';
    }

    public function fetchMetrics(SocialAccount $account, string $token, array $distributions): array
    {
        $out = [];

        foreach ($distributions as $d) {
            $mediaId = (string) $d->external_id;
            // Non-reel media rejects the ig_reels_* metrics (error 100): retry without them.
            $values = $this->insights($mediaId, $token, self::REELS_METRICS)
                ?? $this->insights($mediaId, $token, self::BASIC_METRICS);

            if ($values === null) {
                continue;
            }

            $out[$d->id] = $this->metrics([
                'views' => $values['views'] ?? 0,
                'likes' => $values['likes'] ?? 0,
                'comments' => $values['comments'] ?? 0,
                'shares' => $values['shares'] ?? 0,
                'saves' => $values['saved'] ?? 0,
                'watch_time_seconds' => ($values['ig_reels_video_view_total_time'] ?? 0) / 1000,
                'avg_view_duration_seconds' => ($values['ig_reels_avg_watch_time'] ?? 0) / 1000,
            ]);
        }

        return $out;
    }

    public function fetchFollowers(SocialAccount $account, string $token): ?int
    {
        $igUserId = $account->external_id ?: ($account->meta['ig_user_id'] ?? null);
        if (! $igUserId) {
            return null;
        }

        $response = Http::timeout(self::TIMEOUT)->get($this->graphUrl((string) $igUserId), [
            'fields' => 'followers_count',
            'access_token' => $token,
        ]);
        $this->throwIfMetaRateLimited($response);

        $count = $response->json('followers_count');

        return $response->successful() && $count !== null ? (int) $count : null;
    }

    /** @return array<string, int|float>|null metric name => lifetime value */
    private function insights(string $mediaId, string $token, string $metrics): ?array
    {
        $response = Http::timeout(self::TIMEOUT)->get($this->graphUrl($mediaId.'/insights'), [
            'metric' => $metrics,
            'access_token' => $token,
        ]);
        $this->throwIfMetaRateLimited($response);

        if ($response->failed()) {
            Log::info('analytics.instagram.insights_failed', [
                'media' => $mediaId, 'metrics' => $metrics, 'error' => $response->json('error.message'),
            ]);

            return null;
        }

        $values = [];
        foreach ((array) $response->json('data', []) as $row) {
            $value = $row['total_value']['value'] ?? ($row['values'][0]['value'] ?? 0);
            $values[(string) ($row['name'] ?? '')] = is_numeric($value) ? $value : 0;
        }

        return $values;
    }
}
