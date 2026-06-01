<?php

namespace App\Filament\Pages;

use BackedEnum;
use App\Models\Post;
use App\Models\PostAnalytic;
use App\Models\SocialAccount;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TiktokInsights extends Page
{
    protected static ?string $slug = 'tiktok-insights';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'TikTok Insights';

    protected string $view = 'filament.pages.tiktok-insights';

    protected function tiktokAccount(): ?SocialAccount
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        return $user->socialAccounts()
            ->where('platform_name', 'tiktok')
            ->orWhere('provider', 'tiktok')
            ->first();
    }

    protected function formatCompactNumber(int $number): string
    {
        if ($number >= 1_000_000) {
            return number_format($number / 1_000_000, 1).'M';
        }

        if ($number >= 1_000) {
            return number_format($number / 1_000, 1).'K';
        }

        return number_format($number);
    }

    protected function formatSignedCompactNumber(int $number): string
    {
        return ($number >= 0 ? '+' : '').$this->formatCompactNumber(abs($number));
    }

    public function getTiktokHandle(): string
    {
        return $this->tiktokAccount()?->handle ?? '@creator_studio';
    }

    public function getTotalTiktokViews(): string
    {
        $account = $this->tiktokAccount();

        if (! $account) {
            return number_format(0);
        }

        $views = (int) PostAnalytic::query()
            ->join('posts', 'posts.id', '=', 'post_analytics.post_id')
            ->where('posts.social_account_id', $account->id)
            ->sum('post_analytics.views');

        return $this->formatCompactNumber($views);
    }

    public function getWeeklyTiktokVelocity(): string
    {
        $account = $this->tiktokAccount();

        if (! $account) {
            return '0%';
        }

        $currentStart = now()->subDays(6)->startOfDay();
        $previousStart = now()->subDays(13)->startOfDay();
        $previousEnd = now()->subDays(7)->endOfDay();

        $currentViews = (int) PostAnalytic::query()
            ->join('posts', 'posts.id', '=', 'post_analytics.post_id')
            ->where('posts.social_account_id', $account->id)
            ->whereBetween('post_analytics.recorded_at', [$currentStart, now()->endOfDay()])
            ->sum('post_analytics.views');

        $previousViews = (int) PostAnalytic::query()
            ->join('posts', 'posts.id', '=', 'post_analytics.post_id')
            ->where('posts.social_account_id', $account->id)
            ->whereBetween('post_analytics.recorded_at', [$previousStart, $previousEnd])
            ->sum('post_analytics.views');

        if ($previousViews === 0) {
            return '+0%';
        }

        $delta = (($currentViews - $previousViews) / $previousViews) * 100;

        return ($delta >= 0 ? '+' : '').number_format($delta, 0).'%';
    }

    public function getFollowerGrowth(): string
    {
        $followers = (int) ($this->tiktokAccount()?->follower_count ?? 0);

        return $this->formatSignedCompactNumber($followers);
    }

    public function getEngagementRate(): string
    {
        $account = $this->tiktokAccount();

        if (! $account) {
            return '0.0%';
        }

        $metrics = PostAnalytic::query()
            ->join('posts', 'posts.id', '=', 'post_analytics.post_id')
            ->where('posts.social_account_id', $account->id)
            ->whereDate('post_analytics.recorded_at', '>=', now()->subDays(29)->toDateString())
            ->selectRaw('COALESCE(SUM(post_analytics.views), 0) as views')
            ->selectRaw('COALESCE(SUM(post_analytics.likes), 0) as likes')
            ->selectRaw('COALESCE(SUM(post_analytics.comments), 0) as comments')
            ->selectRaw('COALESCE(SUM(post_analytics.shares), 0) as shares')
            ->first();

        $views = (int) ($metrics->views ?? 0);

        if ($views === 0) {
            return '0.0%';
        }

        $engagement = (((int) ($metrics->likes ?? 0)) + ((int) ($metrics->comments ?? 0)) + ((int) ($metrics->shares ?? 0))) / $views * 100;

        return number_format($engagement, 1).'%';
    }

    public function getPerformanceBars(): array
    {
        $account = $this->tiktokAccount();

        if (! $account) {
            return array_fill(0, 10, 14);
        }

        $dailyViews = PostAnalytic::query()
            ->join('posts', 'posts.id', '=', 'post_analytics.post_id')
            ->where('posts.social_account_id', $account->id)
            ->whereDate('post_analytics.recorded_at', '>=', now()->subDays(29)->toDateString())
            ->selectRaw('DATE(post_analytics.recorded_at) as day, SUM(post_analytics.views) as views')
            ->groupBy(DB::raw('DATE(post_analytics.recorded_at)'))
            ->orderBy(DB::raw('DATE(post_analytics.recorded_at)'))
            ->pluck('views', 'day')
            ->map(fn ($value): int => (int) $value)
            ->all();

        $bars = array_fill(0, 10, 0);

        for ($index = 0; $index < 30; $index++) {
            $day = now()->subDays(29 - $index)->toDateString();
            $bucket = (int) floor($index / 3);
            $bars[$bucket] += (int) ($dailyViews[$day] ?? 0);
        }

        $max = max($bars) ?: 1;

        return array_map(function (int $value) use ($max): int {
            return max(12, (int) round(($value / $max) * 100));
        }, $bars);
    }

    public function getPerformanceAxisLabels(): array
    {
        return [
            now()->subDays(29)->format('M j'),
            now()->subDays(15)->format('M j'),
            now()->format('M j'),
        ];
    }

    public function getRecentTiktokUploads(): array
    {
        $account = $this->tiktokAccount();

        if (! $account) {
            return [];
        }

        return Post::query()
            ->where('social_account_id', $account->id)
            ->select('posts.*')
            ->selectRaw('COALESCE(SUM(post_analytics.views), 0) as total_views')
            ->selectRaw('COALESCE(SUM(post_analytics.likes), 0) as total_likes')
            ->selectRaw('COALESCE(SUM(post_analytics.comments), 0) as total_comments')
            ->leftJoin('post_analytics', 'post_analytics.post_id', '=', 'posts.id')
            ->groupBy('posts.id')
            ->orderByDesc('published_at')
            ->limit(4)
            ->get()
            ->map(function (Post $post, int $index): array {
                $publishedAt = $post->published_at instanceof Carbon ? $post->published_at : Carbon::parse($post->published_at ?? $post->created_at);

                $status = $post->total_views > 0 ? 'Published' : 'Processing';

                return [
                    'title' => $post->title,
                    'views' => $this->formatCompactNumber((int) $post->total_views),
                    'likes' => $this->formatCompactNumber((int) $post->total_likes),
                    'duration' => $this->getDisplayDuration($post->id, $publishedAt),
                    'status' => $status,
                    'progress' => $status === 'Processing' ? 42 : null,
                ];
            })
            ->all();
    }

    public function getSidebarItems(): array
    {
        return [
            [
                'label' => 'Overview',
                'href' => route('filament.admin.pages.dashboard'),
                'icon' => 'dashboard',
                'active' => request()->routeIs('filament.admin.pages.dashboard'),
            ],
            [
                'label' => 'TikTok',
                'href' => route('filament.admin.pages.tiktok-insights'),
                'icon' => 'video_library',
                'active' => request()->routeIs('filament.admin.pages.tiktok-insights'),
            ],
            [
                'label' => 'YouTube',
                'href' => route('integrations.index', ['provider' => 'youtube']),
                'icon' => 'smart_display',
                'active' => false,
            ],
            [
                'label' => 'Instagram',
                'href' => route('integrations.index', ['provider' => 'instagram']),
                'icon' => 'photo_camera',
                'active' => false,
            ],
        ];
    }

    protected function getDisplayDuration(int $seed, Carbon $publishedAt): string
    {
        $durationSeconds = 35 + (($seed + $publishedAt->day) % 55);

        return sprintf('%d:%02d', intdiv($durationSeconds, 60), $durationSeconds % 60);
    }
}
