<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications to the mobile app via Expo's push service.
 * The app registers Expo push tokens (ExponentPushToken[...]) per device.
 */
class ExpoPushService
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    /**
     * Store the in-app notification (always, CONTRACT §11), then push unless the
     * user turned notifications off (notifications_enabled=false).
     */
    public function sendToUser(User $user, string $title, string $body, array $data = [], string $channelId = 'alerts'): void
    {
        try {
            app(NotificationFeed::class)->store($user, $title, $body, $data);
        } catch (\Throwable $e) {
            Log::warning('notification store failed: '.$e->getMessage());
        }

        if ($user->notifications_enabled === false) {
            return;
        }

        $tokens = $user->deviceTokens()->pluck('token')->all();
        $this->send($tokens, $title, $body, $data, $channelId);
    }

    /**
     * @param  array<int, string>  $tokens
     */
    public function send(array $tokens, string $title, string $body, array $data = [], string $channelId = 'alerts'): void
    {
        $tokens = array_values(array_filter(
            $tokens,
            fn ($t) => is_string($t) && str_starts_with($t, 'ExponentPushToken'),
        ));

        if (empty($tokens)) {
            return;
        }

        // Expo accepts up to 100 messages per request.
        foreach (array_chunk($tokens, 100) as $chunk) {
            $messages = array_map(fn ($to) => [
                'to' => $to,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'sound' => 'default',
                'priority' => 'high',
                'channelId' => $channelId,
            ], $chunk);

            try {
                $res = Http::acceptJson()->timeout(10)->post(self::ENDPOINT, $messages);
                $this->pruneInvalidTokens($res->json('data') ?? [], $chunk);
            } catch (\Throwable $e) {
                Log::warning('Expo push send failed: '.$e->getMessage());
            }
        }
    }

    /**
     * Delete tokens Expo reports as no longer registered so we stop sending to them.
     *
     * @param  array<int, array<string, mixed>>  $receipts
     * @param  array<int, string>  $tokens
     */
    private function pruneInvalidTokens(array $receipts, array $tokens): void
    {
        foreach ($receipts as $i => $receipt) {
            $isError = ($receipt['status'] ?? null) === 'error';
            $notRegistered = ($receipt['details']['error'] ?? null) === 'DeviceNotRegistered';
            if ($isError && $notRegistered && isset($tokens[$i])) {
                DeviceToken::where('token', $tokens[$i])->delete();
            }
        }
    }
}
