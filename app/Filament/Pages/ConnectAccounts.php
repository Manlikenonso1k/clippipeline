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

    protected static ?string $navigationLabel = 'Connect';

    protected static ?string $title = 'Connected Accounts';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.connect-accounts';

    /** Platforms each OAuth provider creates rows for. */
    private const PROVIDER_PLATFORMS = [
        'youtube' => ['youtube'],
        'meta' => ['facebook', 'instagram'],
        'tiktok' => ['tiktok'],
    ];

    /** What we access and why, shown before OAuth (COMPLIANCE U-04). */
    public const RATIONALE = [
        'youtube' => 'ClipPipeline uses your YouTube channel to upload the videos you choose to publish and to read their views, likes and comments for Insights. Nothing is posted until you press Publish.',
        'meta' => 'ClipPipeline uses the Facebook Page and Instagram professional account you pick to publish the Reels you choose and to read their views, likes, comments and followers for Insights.',
        'tiktok' => 'ClipPipeline uses your TikTok profile (name, avatar and follower count) to post the videos you choose and to read their views, likes, comments and shares for Insights.',
    ];

    /** Policy links shown on a provider card. */
    public const POLICY_LINKS = [
        'youtube' => [
            ['label' => 'YouTube Terms of Service', 'url' => 'https://www.youtube.com/t/terms'],
            ['label' => 'Google Privacy Policy', 'url' => 'https://policies.google.com/privacy'],
        ],
        'meta' => [],
        'tiktok' => [],
    ];

    private const PLATFORM_LABELS = [
        'youtube' => 'YouTube',
        'facebook' => 'Facebook Page',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
    ];

    /**
     * @return array<int, array{provider: string, label: string, connect_url: string, connected: bool, rationale: string, links: array<int, array{label: string, url: string}>, rows: array<int, array<string, mixed>>}>
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
                'rationale' => self::RATIONALE[$provider],
                'links' => self::POLICY_LINKS[$provider],
                'rows' => $rows,
            ];
        }

        return $cards;
    }

    /** COMPLIANCE U-02: disconnect revokes the token at the platform (DisconnectService). */
    public const DISCONNECT_EXPLANATION = 'ClipPipeline revokes its access at the platform and deletes the stored tokens. Publishing and analytics sync for this platform stop until you connect it again. Facebook and Instagram are removed together. Videos already published stay on the platform.';

    public function disconnectAction(): Action
    {
        return Action::make('disconnect')
            ->label('Disconnect')
            ->color('danger')
            ->icon(Heroicon::OutlinedXCircle)
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Disconnect '.($arguments['label'] ?? 'account').'?')
            ->modalDescription(self::DISCONNECT_EXPLANATION)
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
