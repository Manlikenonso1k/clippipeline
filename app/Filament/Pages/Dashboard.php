<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AnalyticsOverview;
use App\Filament\Widgets\PlatformBreakdown;
use App\Filament\Widgets\SyncStatus;
use App\Filament\Widgets\TopPostsTable;
use App\Filament\Widgets\ViewsTrendChart;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Widgets\AccountWidget;

/** Home: sync status + analytics; the period filter feeds every widget (same periods as /api/analytics/*). */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Home';

    protected static ?string $navigationLabel = 'Home';

    protected static ?int $navigationSort = 0;

    public const PERIOD_OPTIONS = [
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
    ];

    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            SyncStatus::class,
            AnalyticsOverview::class,
            ViewsTrendChart::class,
            PlatformBreakdown::class,
            TopPostsTable::class,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')
                ->label('Period')
                ->options(self::PERIOD_OPTIONS)
                ->default('7d')
                ->selectablePlaceholder(false),
        ]);
    }
}
