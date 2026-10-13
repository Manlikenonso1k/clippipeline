<?php

namespace App\Services\Analytics;

use App\Models\PostDistribution;
use App\Models\SocialAccount;
use Illuminate\Http\Client\Response;

/**
 * Reads lifetime counters for published items and the account's follower count.
 * Implementations throw RateLimitedException on quota errors; any other failure
 * is logged by the caller and that account is skipped.
 */
abstract class PlatformFetcher
{
    public const TIMEOUT = 20;

    abstract public function platform(): string;

    /**
     * @param  array<int, PostDistribution>  $distributions  all with an external_id
     * @return array<int, array<string,int>> keyed by distribution id (views, likes, comments,
     *                                       shares, saves, watch_time_seconds, avg_view_duration_seconds)
     */
    abstract public function fetchMetrics(SocialAccount $account, string $token, array $distributions): array;

    abstract public function fetchFollowers(SocialAccount $account, string $token): ?int;

    /** @return array<string,int> */
    protected function metrics(array $values = []): array
    {
        $base = [
            'views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'saves' => 0,
            'watch_time_seconds' => 0, 'avg_view_duration_seconds' => 0,
        ];

        foreach ($values as $k => $v) {
            if (array_key_exists($k, $base) && is_numeric($v)) {
                $base[$k] = max(0, (int) round((float) $v));
            }
        }

        return $base;
    }

    protected function graphUrl(string $path): string
    {
        $version = config('services.meta.graph_version') ?: 'v24.0';

        return 'https://graph.facebook.com/'.$version.'/'.ltrim($path, '/');
    }

    /** Meta rate-limit codes: 4 (app), 17 (user), 32 (page), 613 (custom), plus HTTP 429. */
    protected function throwIfMetaRateLimited(Response $response): void
    {
        $code = (int) $response->json('error.code');
        if ($response->status() === 429 || in_array($code, [4, 17, 32, 613], true)) {
            throw new RateLimitedException('Meta rate limit: '.($response->json('error.message') ?? $response->status()));
        }
    }
}
