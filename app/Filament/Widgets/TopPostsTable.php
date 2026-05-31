<?php

namespace App\Filament\Widgets;

use App\Models\Post;
use Filament\Tables;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TopPostsTable extends TableWidget
{
    protected static ?string $heading = 'Top Posts';
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $userId = Auth::id() ?? 0;

        return Post::query()
            ->where('posts.user_id', $userId)
            ->leftJoin('post_analytics', 'post_analytics.post_id', '=', 'posts.id')
            ->select('posts.id', 'posts.tiktok_id', 'posts.caption')
            ->selectRaw('COALESCE(SUM(post_analytics.views), 0) as total_views')
            ->selectRaw('COALESCE(SUM(post_analytics.likes), 0) as total_likes')
            ->selectRaw('COALESCE(SUM(post_analytics.comments), 0) as total_comments')
            ->groupBy('posts.id', 'posts.tiktok_id', 'posts.caption')
            ->orderByDesc('total_views');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('tiktok_id')
                ->label('TikTok ID')
                ->searchable(),
            Tables\Columns\TextColumn::make('caption')
                ->limit(50)
                ->wrap(),
            Tables\Columns\TextColumn::make('total_views')
                ->label('Views')
                ->numeric(),
            Tables\Columns\TextColumn::make('total_likes')
                ->label('Likes')
                ->numeric(),
            Tables\Columns\TextColumn::make('total_comments')
                ->label('Comments')
                ->numeric(),
        ];
    }
}
