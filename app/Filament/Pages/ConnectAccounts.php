<?php

namespace App\Filament\Pages;

use App\Models\SocialAccount;
use App\Services\Connect\ConnectService;
use App\Services\Connect\DisconnectService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Connected accounts (docs/CONTRACT.md §3): one card per OAuth provider. Connect /
 * Reconnect go through integrations.redirect (platform=web state); Disconnect uses
 * the same DisconnectService as DELETE /api/social-accounts/{id}.
 */
class ConnectAccounts extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $navigationLabel = 'Connected Accounts';

    protected static ?string $title = 'Connected Accounts';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.connect-accounts';

    /** Platforms each OAuth provider creates rows for. */
    private const PROVIDER_PLATFORMS = [
        'youtube' => ['youtube'],
        'meta' => ['facebook', 'instagram'],
        'tiktok' => ['tiktok'],
    ];

    private const PLATFORM_LABELS = [
        'youtube' => 'YouTube',
        'facebook' => 'Facebook Page',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
    ];

    /**
     * @return array<int, array{provider: string, label: string, connect_url: string, connected: bool, rows: array<int, array<string, mixed>>}>
     */
    public function getProviderCards(): array
    {
        $accounts = SocialAccount::query()
            ->where('user_id', auth()->id())
            ->get()
            ->keyBy('provider');

        $cards = [];
        foreach (self::PROVIDER_PLATFORMS as $provider => $platforms) {
            $rows = [];
            foreach ($platforms as $platform) {
                $account = $accounts->get($platform);
                $status = $account?->effectiveStatus();

                $rows[] = [
                    'platform' => $platform,
                    'label' => self::PLATFORM_LABELS[$platform],
                    'id' => $account?->id,
                    'status' => $status,
                    'handle' => $account?->handle ?: $account?->display_name,
                    'followers' => (int) ($account?->follower_count ?? 0),
                    'expires_at' => $account?->expires_at,
                    'last_synced_at' => $account?->last_synced_at,
                    'error' => $account && $status !== SocialAccount::STATUS_ACTIVE ? $account->last_error : null,
                ];
            }

            $cards[] = [
                'provider' => $provider,
                'label' => ConnectService::label($provider),
                'connect_url' => route('integrations.redirect', ['provider' => $provider]),
                'connected' => collect($rows)->contains(fn (array $r) => $r['id'] !== null),
                'rows' => $rows,
            ];
        }

        return $cards;
    }

    public function disconnectAction(): Action
    {
        return Action::make('disconnect')
            ->label('Disconnect')
            ->color('danger')
            ->icon(Heroicon::OutlinedXCircle)
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Disconnect '.($arguments['label'] ?? 'account').'?')
            ->modalDescription('Publishing to this platform stops until you connect it again. Facebook and Instagram are removed together.')
            ->action(function (array $arguments, DisconnectService $disconnect): void {
                $ids = $disconnect->disconnect(auth()->user(), (int) ($arguments['account'] ?? 0));

                if ($ids === null) {
                    Notification::make()->title('Account not found.')->danger()->send();

                    return;
                }

                Notification::make()->title(($arguments['label'] ?? 'Account').' disconnected')->success()->send();
            });
    }
}
