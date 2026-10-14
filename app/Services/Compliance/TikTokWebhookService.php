<?php

namespace App\Services\Compliance;

use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Services\ExpoPushService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TikTok webhooks (COMPLIANCE C-08; developers.tiktok.com/doc/webhooks-verification,
 * /doc/webhooks-events, Content Posting status events). Header
 * `TikTok-Signature: t=<unix>,s=<hex>` where s = HMAC-SHA256(client_secret, "<t>.<raw body>").
 * Handlers are idempotent (TikTok delivers at least once).
 */
class TikTokWebhookService
{
    public function verify(?string $header, string $body, ?int $now = null): bool
    {
        $secret = (string) (config('services.tiktok_webhook.secret') ?: config('services.tiktok.client_secret'));
        if ($secret === '' || ! is_string($header) || $header === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $element) {
            [$k, $v] = array_pad(explode('=', trim($element), 2), 2, '');
            $parts[$k] = $v;
        }

        $timestamp = $parts['t'] ?? '';
        $signature = strtolower($parts['s'] ?? '');
        if (! ctype_digit($timestamp) || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        if (! hash_equals($expected, $signature)) {
            return false;
        }

        $tolerance = (int) config('services.tiktok_webhook.tolerance_seconds', 300);

        return abs(($now ?? time()) - (int) $timestamp) <= $tolerance;
    }

    /** @param  array<string, mixed>  $event */
    public function handle(array $event): void
    {
        $name = (string) ($event['event'] ?? '');
        $content = is_string($event['content'] ?? null) ? (array) json_decode($event['content'], true) : (array) ($event['content'] ?? []);
        $openId = (string) ($event['user_openid'] ?? '');

        try {
            match (true) {
                $name === 'authorization.removed' => $this->authorizationRemoved($openId, (int) ($content['reason'] ?? 0)),
                str_starts_with($name, 'post.publish.') => $this->publishEvent($name, $content),
                default => null,
            };
        } catch (Throwable $e) {
            Log::warning('tiktok.webhook_failed', ['event' => $name, 'error' => $e->getMessage()]);
        }
    }

    /** Token already revoked by TikTok: purge tokens, flag the row, stop queued publishes. */
    private function authorizationRemoved(string $openId, int $reason): void
    {
        if ($openId === '') {
            return;
        }

        $accounts = SocialAccount::query()->where('provider', 'tiktok')->get()
            ->filter(fn (SocialAccount $a) => $a->external_id === $openId || (string) data_get($a->meta, 'open_id') === $openId);

        foreach ($accounts as $account) {
            $alreadyRevoked = $account->status === SocialAccount::STATUS_NEEDS_REAUTH && ! $account->access_token;

            $account->forceFill([
                'access_token' => null,
                'refresh_token' => null,
                'expires_at' => null,
                'status' => SocialAccount::STATUS_NEEDS_REAUTH,
                'needs_attention' => true,
                'last_error' => 'TikTok access was removed (reason '.$reason.'). Reconnect TikTok to keep posting.',
                'reauth_required_at' => $account->reauth_required_at ?? now(),
            ])->save();

            PostDistribution::query()
                ->where('platform', 'tiktok')
                ->whereIn('status', [PostDistribution::STATUS_QUEUED, 'pending'])
                ->whereHas('post', fn ($q) => $q->where('user_id', $account->user_id))
                ->get()
                ->each(fn (PostDistribution $d) => $d->forceFill([
                    'status' => PostDistribution::STATUS_FAILED,
                    'error' => 'TikTok access was removed.',
                    'error_message' => 'TikTok access was removed.',
                ])->saveQuietly());

            if (! $alreadyRevoked && $account->user) {
                app(ExpoPushService::class)->sendToUser(
                    $account->user,
                    'Reconnect TikTok',
                    'ClipPipeline lost access to your TikTok account. Tap to reconnect.',
                    ['type' => 'account_needs_reauth', 'screen' => 'connect', 'provider' => 'tiktok'],
                );
            }
        }
    }

    /** @param  array<string, mixed>  $content */
    private function publishEvent(string $name, array $content): void
    {
        $publishId = (string) ($content['publish_id'] ?? '');
        if ($publishId === '') {
            return;
        }

        $distribution = PostDistribution::query()
            ->where('platform', 'tiktok')
            ->where(fn ($q) => $q->where('external_id', $publishId)->orWhere('meta->tiktok_publish_id', $publishId))
            ->first();

        if (! $distribution) {
            return;
        }

        $meta = (array) $distribution->meta;

        switch ($name) {
            case 'post.publish.failed':
                if (! $distribution->isPublished()) {
                    $reason = (string) ($content['reason'] ?? 'unknown reason');
                    $distribution->forceFill([
                        'status' => PostDistribution::STATUS_FAILED,
                        'error' => 'TikTok could not publish the video: '.$reason,
                        'error_message' => 'TikTok could not publish the video: '.$reason,
                    ])->save();
                }
                break;

            case 'post.publish.complete':
                if (! $distribution->isPublished()) {
                    unset($meta['awaiting_poll'], $meta['polls']);
                    $distribution->forceFill([
                        'status' => PostDistribution::STATUS_PUBLISHED,
                        'external_id' => $distribution->external_id ?: $publishId,
                        'published_at' => $distribution->published_at ?? now(),
                        'error' => null,
                        'error_message' => null,
                        'meta' => $meta + ['tiktok_publish_id' => $publishId],
                    ])->save();
                }
                break;

            case 'post.publish.publicly_available':
                $postId = (string) ($content['post_id'] ?? '');
                if ($postId !== '') {
                    $handle = ltrim((string) SocialAccount::query()->where('user_id', $distribution->post?->user_id)->where('provider', 'tiktok')->value('handle'), '@');
                    $meta['tiktok_publish_id'] = $publishId;
                    $meta['url'] = $handle !== '' ? "https://www.tiktok.com/@{$handle}/video/{$postId}" : ($meta['url'] ?? null);
                    $distribution->forceFill([
                        'status' => PostDistribution::STATUS_PUBLISHED,
                        'external_id' => $postId,
                        'published_at' => $distribution->published_at ?? now(),
                        'meta' => $meta,
                    ])->save();
                }
                break;
        }
    }
}
