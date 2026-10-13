<?php

namespace App\Services\Connect;

use App\Exceptions\NeedsReauthException;
use App\Models\SocialAccount;
use App\Services\ExpoPushService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The one place publish jobs and analytics fetchers get a usable access token.
 * Owner: CONNECT agent. Callers rely only on freshAccessToken()'s signature.
 */
class TokenManager
{
    /** Refresh when the token expires within this many seconds. */
    public const REFRESH_MARGIN_SECONDS = 300;

    /** Re-validate Meta Page tokens (no refresh exists) at most this often. */
    public const META_VALIDATE_EVERY_HOURS = 6;

    public function __construct(private ConnectService $connect, private ExpoPushService $push) {}

    /**
     * Return a non-expired access token for the account, refreshing it if needed.
     *
     * @throws NeedsReauthException when refresh fails (account is marked needs_reauth).
     * @throws RuntimeException on a transient provider failure (safe to retry later).
     */
    public function freshAccessToken(SocialAccount $account): string
    {
        if ($account->status === SocialAccount::STATUS_NEEDS_REAUTH) {
            throw new NeedsReauthException("The {$account->provider} account needs to be reconnected.");
        }

        if (empty($account->access_token)) {
            $this->markNeedsReauth($account, 'No access token stored; reconnect the account.');
        }

        if ($this->isDue($account, self::REFRESH_MARGIN_SECONDS)) {
            $this->refresh($account);
        }

        return (string) $account->access_token;
    }

    /** Whether the account should be refreshed/validated now (window in seconds). */
    public function isDue(SocialAccount $account, int $withinSeconds): bool
    {
        if ($this->isMeta($account)) {
            if ($account->expires_at && $account->expires_at->lte(now()->addSeconds($withinSeconds))) {
                return true;
            }
            $validated = data_get($account->meta, 'validated_at');

            return ! $validated || Carbon::parse($validated)->lt(now()->subHours(self::META_VALIDATE_EVERY_HOURS));
        }

        // Unknown expiry with a refresh token: refresh to learn it.
        if (! $account->expires_at) {
            return (bool) $account->refresh_token;
        }

        return $account->expires_at->lte(now()->addSeconds($withinSeconds));
    }

    /**
     * Refresh (or validate) now and persist. Marks needs_reauth on a definitive failure.
     *
     * @throws NeedsReauthException
     * @throws RuntimeException on transient failure
     */
    public function refresh(SocialAccount $account): void
    {
        try {
            $this->connect->connector($this->connectProvider($account))->refresh($account);
        } catch (TokenRefreshException $e) {
            $account->refresh(); // drop partially-filled attributes

            if (! $e->definitive) {
                // Meta validation is advisory: keep using the stored token while Meta is unreachable.
                if ($this->isMeta($account) && ! $account->isExpired()) {
                    return;
                }

                throw new RuntimeException($e->getMessage(), 0, $e);
            }

            $this->markNeedsReauth($account, $e->getMessage());
        }

        // An `error` row (e.g. Instagram with no linked business account) stays in error.
        $healthy = $account->status !== SocialAccount::STATUS_ERROR;

        $account->forceFill([
            'status' => $healthy ? SocialAccount::STATUS_ACTIVE : SocialAccount::STATUS_ERROR,
            'last_error' => $healthy ? null : $account->last_error,
        ])->save();
    }

    /**
     * @throws NeedsReauthException always
     */
    public function markNeedsReauth(SocialAccount $account, string $reason): never
    {
        $wasFlagged = $account->status === SocialAccount::STATUS_NEEDS_REAUTH;
        $provider = $this->connectProvider($account);
        $values = ['status' => SocialAccount::STATUS_NEEDS_REAUTH, 'last_error' => $reason, 'needs_attention' => true];

        // Meta rows share one token: flag both.
        $rows = $this->isMeta($account)
            ? SocialAccount::where('user_id', $account->user_id)->whereIn('provider', ['facebook', 'instagram'])->get()
            : collect([$account]);

        foreach ($rows as $row) {
            $row->forceFill($values)->save();
        }
        $account->forceFill($values);

        if (! $wasFlagged && $account->user) {
            try {
                $label = ConnectService::label($provider);
                $this->push->sendToUser(
                    $account->user,
                    "Reconnect {$label}",
                    "ClipPipeline lost access to your {$label} account. Tap to reconnect.",
                    ['type' => 'account_needs_reauth', 'screen' => 'connect', 'provider' => $provider],
                );
            } catch (Throwable $e) {
                Log::warning('needs_reauth push failed: '.$e->getMessage());
            }
        }

        throw new NeedsReauthException("The {$account->provider} account needs to be reconnected: {$reason}");
    }

    private function connectProvider(SocialAccount $account): string
    {
        return ConnectService::normalize((string) $account->provider) ?? (string) $account->provider;
    }

    private function isMeta(SocialAccount $account): bool
    {
        return in_array($account->provider, ['facebook', 'instagram'], true);
    }
}
