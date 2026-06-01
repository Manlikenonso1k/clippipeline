<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;

class TiktokInsights extends Page
{
    protected static ?string $slug = 'tiktok-insights';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'TikTok Insights';

    protected string $view = 'filament.pages.tiktok-insights';

    public function getTotalTiktokViews(): string
    {
        return number_format(0);
    }

    public function getWeeklyTiktokVelocity(): string
    {
        return '0%';
    }

    public function getFollowerGrowth(): string
    {
        return '+0';
    }

    public function getEngagementRate(): string
    {
        return '0.0%';
    }

    public function getPerformanceBars(): array
    {
        return [28, 36, 18, 52, 68, 44, 34, 74, 58, 30];
    }

    public function getRecentTiktokUploads(): array
    {
        return [
            [
                'title' => 'Top 5 mechanical keyboards for editors',
                'views' => '45.2K',
                'likes' => '4.1K',
                'duration' => '0:45',
                'status' => 'Published',
            ],
            [
                'title' => 'Why retro tech is making a comeback',
                'views' => '12.8K',
                'likes' => '950',
                'duration' => '1:12',
                'status' => 'Published',
            ],
            [
                'title' => 'Processing: Setup tour 2024',
                'views' => '0',
                'likes' => '0',
                'duration' => '--',
                'status' => 'Processing',
                'progress' => 42,
            ],
        ];
    }
}
