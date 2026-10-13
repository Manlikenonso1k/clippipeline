<?php

namespace App\Services\Analytics;

use App\Models\SocialAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * TikTok Display API: POST /v2/video/query/ (max 20 ids per call) and
 * GET /v2/user/info/ (follower_count, scope user.info.stats). TikTok does not
 * report saves or watch time here, so those stay 0.
 */
class TikTokFetcher extends PlatformFetcher
{
    public function platform(): string
    {
        return 'tiktok';
    }

    public function fetchMetrics(SocialAccount $account, string $token, array $distributions): array
    {
        $out = [];

        foreach (array_chunk($distributions, 20) as $chunk) {
            $byVideo = [];
            foreach ($chunk as $d) {
                $byVideo[(string) $d->external_id] = $d;
            }

            $response = Http::withToken($token)->timeout(self::TIMEOUT)->asJson()
                ->post('https://open.tiktokapis.com/v2/video/query/?fields=id,view_count,like_count,comment_count,share_count', [
                    'filters' => ['video_ids' => array_map('strval', array_keys($byVideo))],
                ]);
            $this->throwIfLimited($response);
            if ($response->failed() || ! in_array($response->json('error.code'), [null, 'ok'], true)) {
                throw new RuntimeException('TikTok video/query failed: '.$response->status().' '.$response->json('error.code'));
            }

            foreach ((array) $response->json('data.videos', []) as $video) {
                $d = $byVideo[(string) ($video['id'] ?? '')] ?? null;
                if ($d) {
                    $out[$d->id] = $this->metrics([
                        'views' => $video['view_count'] ?? 0,
                        'likes' => $video['like_count'] ?? 0,
                        'comments' => $video['comment_count'] ?? 0,
                        'shares' => $video['share_count'] ?? 0,
                    ]);
                }
            }
        }

        return $out;
    }

    public function fetchFollowers(SocialAccount $account, string $token): ?int
    {
        $response = Http::withToken($token)->timeout(self::TIMEOUT)
            ->get('https://open.tiktokapis.com/v2/user/info/', ['fields' => 'follower_count']);
        $this->throwIfLimited($response);

        $count = $response->json('data.user.follower_count');

        return $response->successful() && $count !== null ? (int) $count : null;
    }

    private function throwIfLimited(Response $response): void
    {
        if ($response->status() === 429 || $response->json('error.code') === 'rate_limit_exceeded') {
            throw new RateLimitedException('TikTok rate limit');
        }
    }
}
