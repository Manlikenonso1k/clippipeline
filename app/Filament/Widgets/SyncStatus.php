<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ConnectAccounts;
use App\Models\User;
use App\Services\Account\MeService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/** "Auto-sync" status on Home (PARITY H3): MeService `sync`, same as GET /api/me. */
class SyncStatus extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    private const LABELS = [
        'active' => ['Auto-sync active', 'success'],
        'idle' => ['Auto-sync idle', 'gray'],
        'attention' => ['Needs attention', 'warning'],
    ];

    /** @return array{status: string, last_synced_at: ?string} */
    public function sync(): array
    {
        /** @var User $user */
        $user = auth()->user();

        return app(MeService::class)->present($user)['sync'];
    }

    protected function getColumns(): int|array
    {
        return 1;
    }

    protected function getStats(): array
    {
        $sync = $this->sync();
        [$label, $color] = self::LABELS[$sync['status']] ?? [ucfirst((string) $sync['status']), 'gray'];
        $last = $sync['last_synced_at'] ? Carbon::parse($sync['last_synced_at']) : null;

        $description = $last ? 'Last synced '.$last->diffForHumans() : 'Not synced yet';
        if ($sync['status'] === 'attention') {
            $description .= ' · reconnect an account on the Connect page';
        }

        return [
            Stat::make('Analytics sync', $label)
                ->description($description)
                ->descriptionIcon($sync['status'] === 'attention' ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-arrow-path')
                ->color($color)
                ->url($sync['status'] === 'attention' ? ConnectAccounts::getUrl() : null),
        ];
    }
}
