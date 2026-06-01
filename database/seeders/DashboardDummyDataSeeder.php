<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostAnalytic;
use App\Models\SocialAccount;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $user = User::query()->first() ?? User::factory()->create([
                'name' => 'Clippieline Demo User',
                'email' => 'demo@clippieline.test',
            ]);

            $platforms = [
                'tiktok' => '@clip_tiktok',
                'youtube' => '@clip_youtube',
                'instagram' => '@clip_instagram',
            ];

            foreach ($platforms as $platform => $handle) {
                $socialAccount = SocialAccount::query()->create([
                    'user_id' => $user->id,
                    'provider' => $platform,
                    'platform_name' => $platform,
                    'handle' => $handle,
                    'follower_count' => random_int(5_000, 500_000),
                    'access_token' => Str::random(64),
                    'refresh_token' => Str::random(64),
                    'expires_at' => now()->addMonths(6),
                ]);

                $postCount = random_int(20, 30);

                for ($index = 1; $index <= $postCount; $index++) {
                    $publishedAt = Carbon::instance(fake()->dateTimeBetween('-30 days', 'now'))->startOfDay()->addHours(random_int(8, 22));
                    $title = ucfirst($platform).' campaign video '.$index;
                    $videoSlug = Str::slug($platform.' '.$title.' '.$index);
                    $post = Post::query()->create([
                        'user_id' => $user->id,
                        'social_account_id' => $socialAccount->id,
                        'title' => $title,
                        'video_url' => 'https://cdn.clippipeline.test/videos/'.$videoSlug.'.mp4',
                        'published_at' => $publishedAt,
                        'tiktok_id' => $platform === 'tiktok' ? 'tt_'.fake()->unique()->numerify('###########') : null,
                        'caption' => fake()->realText(120),
                        'download_path' => 'videos/'.$videoSlug.'.mp4',
                        'original_url' => 'https://example.com/'.$platform.'/'.$videoSlug,
                        'downloaded_at' => $publishedAt->copy()->addHours(random_int(1, 8)),
                        'meta' => [
                            'platform' => $platform,
                            'seeded' => true,
                        ],
                    ]);

                    foreach (CarbonPeriod::create($publishedAt->startOfDay(), now()->startOfDay()) as $day) {
                        $ageDays = max(0, $day->diffInDays(now()->startOfDay()));
                        $baseViews = match ($platform) {
                            'youtube' => random_int(2_000, 15_000),
                            'instagram' => random_int(1_500, 12_000),
                            default => random_int(3_000, 20_000),
                        };

                        $growthFactor = max(1, (int) round(1 + ($ageDays * random_int(3, 12))));
                        $views = $baseViews + ($growthFactor * random_int(60, 180));
                        $likes = (int) round($views * random_float(0.05, 0.10));
                        $comments = (int) round($views * random_float(0.005, 0.02));
                        $shares = (int) round($views * random_float(0.01, 0.04));
                        $watchTime = max(30, (int) round($views * random_float(2.5, 5.5)));

                        PostAnalytic::query()->create([
                            'post_id' => $post->id,
                            'platform' => $platform,
                            'views' => $views,
                            'likes' => $likes,
                            'comments' => $comments,
                            'shares' => $shares,
                            'watch_time' => $watchTime,
                            'recorded_at' => $day->copy()->setTime(23, 59, 59),
                        ]);
                    }
                }
            }
        });
    }
}

if (! function_exists('random_float')) {
    function random_float(float $min, float $max): float
    {
        return $min + (lcg_value() * ($max - $min));
    }
}
