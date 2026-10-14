<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Models\User;
use App\Services\NotificationFeed;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * Bell list (docs/CONTRACT.md §11, PARITY X2): the same NotificationFeed the
 * API's GET /notifications uses. Reached from the bell in the top bar.
 */
class Notifications extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $title = 'Notifications';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.notifications';

    public const LIMIT = 30;

    /** Unread count for the bell badge; 0 when the feed is unavailable. */
    public static function unreadCount(): int
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return 0;
        }

        try {
            return (int) (app(NotificationFeed::class)->list($user, 1)['unread_count'] ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /** The exact array GET /api/notifications?limit=30 returns. */
    public function feed(): array
    {
        /** @var User $user */
        $user = auth()->user();

        return app(NotificationFeed::class)->list($user, self::LIMIT);
    }

    /** Where a notification leads (same targets as the push payload `screen`). */
    public function linkFor(array $item): ?string
    {
        $data = (array) ($item['data'] ?? []);

        if (! empty($data['postId'])) {
            return PostResource::getUrl('view', ['record' => (int) $data['postId']]);
        }

        if (! empty($data['provider']) || ($item['type'] ?? null) === 'account_needs_reauth') {
            return ConnectAccounts::getUrl();
        }

        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAllRead')
                ->label('Mark all as read')
                ->icon(Heroicon::OutlinedCheck)
                ->color('gray')
                ->action(function (NotificationFeed $feed): void {
                    /** @var User $user */
                    $user = auth()->user();
                    $feed->markAllRead($user);

                    Notification::make()->title('All notifications marked as read')->success()->send();
                }),
        ];
    }
}
