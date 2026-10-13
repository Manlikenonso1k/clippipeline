<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Posts\PostResource;
use App\Filament\Widgets\Concerns\UsesAnalyticsPeriod;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Posts ranked by views gained in the period (AnalyticsService::topPosts, §5.5). */
class TopPostsTable extends TableWidget
{
    use UsesAnalyticsPeriod;

    public const LIMIT = 10;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    /** The exact array GET /api/analytics/top-posts?limit=10 returns for this user and period. */
    public function topPosts(): array
    {
        return $this->analytics()->topPosts($this->user(), $this->period(), self::LIMIT);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Top posts')
            ->description('Ranked by views gained in the selected period.')
            ->records(fn (): array => collect($this->topPosts())
                ->mapWithKeys(fn (array $row) => [$row['post_id'] => [
                    'post_id' => $row['post_id'],
                    'title' => $row['title'] ?? '—',
                    'platforms' => implode(', ', array_map('ucfirst', $row['platforms'])),
                    ...$row['totals'],
                ]])
                ->all())
            ->columns([
                TextColumn::make('title')
                    ->limit(50)
                    ->url(fn (array $record): string => PostResource::getUrl('view', ['record' => $record['post_id']])),
                TextColumn::make('platforms'),
                TextColumn::make('views')->numeric(),
                TextColumn::make('likes')->numeric(),
                TextColumn::make('comments')->numeric(),
                TextColumn::make('shares')->numeric(),
                TextColumn::make('saves')->numeric(),
            ])
            ->emptyStateHeading('No views in this period yet')
            ->paginated(false);
    }
}
