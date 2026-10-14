<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\InsightsTrendChart;
use App\Filament\Widgets\PlatformBreakdown;
use App\Filament\Widgets\TopPostsTable;
use App\Models\User;
use App\Services\Account\MeService;
use App\Services\Analytics\ExportService;
use App\Services\AnalyticsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Insights (PARITY I1/I2): the mobile Insights tab — period + platform filters,
 * timeseries, platform breakdown, top posts and CSV export. Numbers come only
 * from AnalyticsService / ExportService (same as /api/analytics/*).
 */
class Insights extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'insights';

    protected static ?string $title = 'Insights';

    protected static ?string $navigationLabel = 'Insights';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 3;

    public const PLATFORM_OPTIONS = [
        'all' => 'All platforms',
        'youtube' => 'YouTube',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
    ];

    public function getWidgets(): array
    {
        return [
            InsightsTrendChart::class,
            PlatformBreakdown::class,
            TopPostsTable::class,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')
                ->label('Period')
                ->options(Dashboard::PERIOD_OPTIONS)
                ->default('7d')
                ->selectablePlaceholder(false),
            Select::make('platform')
                ->label('Platform')
                ->options(self::PLATFORM_OPTIONS)
                ->default('all')
                ->selectablePlaceholder(false),
        ]);
    }

    public function period(): string
    {
        $period = (string) ($this->filters['period'] ?? '7d');

        return array_key_exists($period, AnalyticsService::PERIODS) ? $period : '7d';
    }

    /** "Last updated" (COMPLIANCE U-05): latest analytics sync for this user. */
    public function lastUpdated(): ?Carbon
    {
        try {
            $at = app(MeService::class)->present($this->user())['sync']['last_synced_at'] ?? null;
        } catch (Throwable) {
            $at = null;
        }

        return $at ? Carbon::parse($at) : null;
    }

    public function getSubheading(): ?string
    {
        $last = $this->lastUpdated();

        return $last ? 'Last updated '.$last->diffForHumans().' ('.$last->utc()->format('M j, H:i').' UTC)' : 'Not synced yet';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (ExportService $export): StreamedResponse {
                    $period = $this->period();
                    $csv = $export->csv($this->user(), $period);

                    return response()->streamDownload(
                        function () use ($csv): void {
                            echo $csv;
                        },
                        ExportService::filename($period),
                        ['Content-Type' => 'text/csv'],
                    );
                }),
        ];
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
