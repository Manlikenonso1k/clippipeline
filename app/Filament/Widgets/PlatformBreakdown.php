<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesAnalyticsPeriod;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Per-platform totals for the period (AnalyticsService::platforms, §5.2). */
class PlatformBreakdown extends TableWidget
{
    use UsesAnalyticsPeriod;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    /** The exact array GET /api/analytics/platforms returns for this user and period. */
    public function platforms(): array
    {
        return $this->analytics()->platforms($this->user(), $this->period());
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('By platform')
            ->records(fn (): array => collect($this->platforms()['platforms'])
                ->mapWithKeys(fn (array $row) => [$row['platform'] => [
                    'platform' => ucfirst($row['platform']),
                    'connected' => $row['connected'],
                    'followers' => $row['followers'],
                    'posts_published' => $row['posts_published'],
                    ...$row['totals'],
                    'views_change' => self::formatChange($row['change']['views']),
                ]])
                ->all())
            ->columns([
                TextColumn::make('platform'),
                IconColumn::make('connected')->boolean(),
                TextColumn::make('followers')->numeric(),
                TextColumn::make('posts_published')->label('Posts')->numeric(),
                TextColumn::make('views')->numeric(),
                TextColumn::make('views_change')->label('Views vs previous')->placeholder('—'),
                TextColumn::make('likes')->numeric(),
                TextColumn::make('comments')->numeric(),
                TextColumn::make('shares')->numeric(),
                TextColumn::make('saves')->numeric(),
            ])
            ->paginated(false);
    }
}
