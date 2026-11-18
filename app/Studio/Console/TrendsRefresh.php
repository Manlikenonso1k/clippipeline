<?php

namespace App\Studio\Console;

use App\Studio\Models\StudioTrend;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Pulls YouTube's most popular videos per region (YouTube Data API, key in STUDIO_YOUTUBE_API_KEY)
 * into studio_trends. TikTok/Instagram rows are curated in /admin (no public trending API exists).
 */
class TrendsRefresh extends Command
{
    protected $signature = 'studio:trends-refresh {--regions=NG,US,GB}';

    protected $description = 'Refresh YouTube trending topics for the Studio "Make a video" flow.';

    public function handle(): int
    {
        $key = (string) config('clippipeline.studio.youtube_api_key');
        if ($key === '') {
            $this->warn('STUDIO_YOUTUBE_API_KEY is not set; skipping YouTube trends.');

            return self::SUCCESS;
        }

        foreach (array_filter(array_map('trim', explode(',', (string) $this->option('regions')))) as $region) {
            $res = Http::timeout(20)->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'snippet,statistics', 'chart' => 'mostPopular', 'regionCode' => strtoupper($region), 'maxResults' => 25, 'key' => $key,
            ]);
            if (! $res->successful()) {
                $this->error("YouTube {$region}: HTTP {$res->status()}");

                continue;
            }
            StudioTrend::query()->where('platform', 'youtube')->where('region', strtoupper($region))->where('source', 'youtube')->delete();
            foreach ((array) $res->json('items', []) as $item) {
                $title = (string) data_get($item, 'snippet.title', '');
                if ($title === '') {
                    continue;
                }
                $tags = array_slice(array_values(array_filter(array_map(
                    fn ($t) => preg_replace('/[^\p{L}\p{N}]/u', '', (string) $t),
                    (array) data_get($item, 'snippet.tags', []),
                ))), 0, 6);
                StudioTrend::create([
                    'platform' => 'youtube', 'region' => strtoupper($region), 'source' => 'youtube',
                    'title' => mb_substr($title, 0, 120), 'hook' => null, 'hashtags' => $tags,
                    'url' => 'https://www.youtube.com/watch?v='.data_get($item, 'id'),
                    'score' => (int) data_get($item, 'statistics.viewCount', 0),
                    'expires_at' => now()->addDays(2), 'is_active' => true,
                ]);
            }
            $this->info("YouTube {$region}: ".count((array) $res->json('items', [])).' trending videos.');
        }

        return self::SUCCESS;
    }
}
