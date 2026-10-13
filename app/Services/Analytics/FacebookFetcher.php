<?php

namespace App\Services\Analytics;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Facebook Page video insights (lifetime). Reels metrics are tried first
 * (blue_reels_play_count, post_video_view_time, post_video_avg_time_watched,
 * post_video_social_actions, post_video_likes_by_reaction_type); regular videos
 * fall back to total_video_views / total_video_view_total_time /
 * total_video_avg_time_watched. Time metrics are milliseconds. Likes and
 * comments also come from the video node's edge summaries.
 */
class FacebookFetcher extends PlatformFetcher
{
    public const REELS_METRICS = 'blue_reels_play_count,post_video_view_time,post_video_avg_time_watched,post_video_social_actions,post_video_likes_by_reaction_type';

    public const VIDEO_METRICS = 'total_video_views,total_video_view_total_time,total_video_avg_time_watched';

    public function platform(): string
    {
        return 'facebook';
    }

    public function fetchMetrics(SocialAccount $account, string $token, array $distributions): array
    {
        $out = [];

        foreach ($distributions as $d) {
            $videoId = (string) $d->external_id;

            $node = Http::timeout(self::TIMEOUT)->get($this->graphUrl($videoId), [
                'fields' => 'likes.summary(true).limit(0),comments.summary(true).limit(0)',
                'access_token' => $token,
            ]);
            $this->throwIfMetaRateLimited($node);

            $reels = $this->insights($videoId, $token, self::REELS_METRICS);
            $video = $reels === null ? $this->insights($videoId, $token, self::VIDEO_METRICS) : null;

            if ($node->failed() && $reels === null && $video === null) {
                Log::info('analytics.facebook.video_failed', ['video' => $videoId, 'error' => $node->json('error.message')]);

                continue;
            }

            $social = is_array($reels['post_video_social_actions'] ?? null)
                ? array_change_key_case($reels['post_video_social_actions'], CASE_LOWER)
                : [];
            $reactions = $reels['post_video_likes_by_reaction_type'] ?? null;
            $reactionSum = is_array($reactions) ? array_sum(array_filter($reactions, 'is_numeric')) : 0;

            $out[$d->id] = $this->metrics([
                'views' => $reels['blue_reels_play_count'] ?? ($video['total_video_views'] ?? 0),
                'likes' => max($reactionSum, (int) $node->json('likes.summary.total_count', 0)),
                'comments' => max((int) ($social['comment'] ?? 0), (int) $node->json('comments.summary.total_count', 0)),
                'shares' => $social['share'] ?? 0,
                'saves' => 0,
                'watch_time_seconds' => (float) ($reels['post_video_view_time'] ?? ($video['total_video_view_total_time'] ?? 0)) / 1000,
                'avg_view_duration_seconds' => (float) ($reels['post_video_avg_time_watched'] ?? ($video['total_video_avg_time_watched'] ?? 0)) / 1000,
            ]);
        }

        return $out;
    }

    public function fetchFollowers(SocialAccount $account, string $token): ?int
    {
        $pageId = $account->external_id ?: ($account->meta['page_id'] ?? null);
        if (! $pageId) {
            return null;
        }

        $response = Http::timeout(self::TIMEOUT)->get($this->graphUrl((string) $pageId), [
            'fields' => 'followers_count,fan_count',
            'access_token' => $token,
        ]);
        $this->throwIfMetaRateLimited($response);
        if ($response->failed()) {
            return null;
        }

        $count = $response->json('followers_count') ?? $response->json('fan_count');

        return $count === null ? null : (int) $count;
    }

    /** @return array<string, mixed>|null */
    private function insights(string $videoId, string $token, string $metrics): ?array
    {
        $response = Http::timeout(self::TIMEOUT)->get($this->graphUrl($videoId.'/video_insights'), [
            'metric' => $metrics,
            'period' => 'lifetime',
            'access_token' => $token,
        ]);
        $this->throwIfMetaRateLimited($response);

        if ($response->failed()) {
            return null;
        }

        $values = [];
        foreach ((array) $response->json('data', []) as $row) {
            $values[(string) ($row['name'] ?? '')] = $row['values'][0]['value'] ?? 0;
        }

        return $values === [] ? null : $values;
    }
}
