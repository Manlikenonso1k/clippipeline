<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/**
 * In-app notification bell (CONTRACT §11) on Laravel's `notifications` table.
 * Rows also carry Filament's database-notification keys so the web bell can show them.
 */
class NotificationFeed
{
    public const TYPES = ['distribution_published', 'distribution_failed', 'account_needs_reauth'];

    /** Store one notification for the user (called by ExpoPushService::sendToUser). */
    public function store(User $user, string $title, string $body, array $data = []): void
    {
        $type = in_array($data['type'] ?? null, self::TYPES, true) ? $data['type'] : 'distribution_published';

        DatabaseNotification::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'data' => [
                // Filament database notification shape.
                'format' => 'filament',
                'title' => $title,
                'body' => $body,
                'status' => $type === 'distribution_published' ? 'success' : 'danger',
                'icon' => null,
                'iconColor' => null,
                'duration' => 'persistent',
                'actions' => [],
                'view' => 'filament-notifications::notification',
                'viewData' => [],
                // Contract payload.
                'payload' => array_filter([
                    'postId' => isset($data['postId']) ? (int) $data['postId'] : null,
                    'provider' => $data['provider'] ?? null,
                ], fn ($v) => $v !== null),
            ],
            'read_at' => null,
        ]);
    }

    /** @return array{unread_count: int, items: array<int, array<string, mixed>>} */
    public function list(User $user, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));

        $items = $user->notifications()
            ->whereIn('type', self::TYPES)
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (DatabaseNotification $n) => [
                'id' => (string) $n->id,
                'type' => (string) $n->type,
                'title' => (string) ($n->data['title'] ?? ''),
                'body' => (string) ($n->data['body'] ?? ''),
                'data' => (object) ($n->data['payload'] ?? []),
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'unread_count' => $user->unreadNotifications()->whereIn('type', self::TYPES)->count(),
            'items' => $items,
        ];
    }

    public function markAllRead(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
