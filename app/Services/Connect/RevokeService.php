<?php

namespace App\Services\Connect;

use App\Models\SocialAccount;
use App\Services\Connect\Providers\MetaConnector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Revoke our grant at the platform before deleting local rows (COMPLIANCE A-01).
 * Best effort: a failure is logged (never the token) and never blocks the delete.
 */
class RevokeService
{
    public const GOOGLE_REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    public const TIKTOK_REVOKE_URL = 'https://open.tiktokapis.com/v2/oauth/revoke/';

    /** @return bool true when the platform confirmed the revoke */
    public function revoke(SocialAccount $account): bool
    {
        try {
            return match ($account->connectProvider()) {
                'youtube' => $this->google($account),
                'tiktok' => $this->tiktok($account),
                'meta' => $this->meta($account),
                default => false,
            };
        } catch (Throwable $e) {
            Log::warning('connect.revoke_failed', ['provider' => $account->provider, 'account' => $account->id, 'error' => class_basename($e)]);

            return false;
        }
    }

    private function google(SocialAccount $account): bool
    {
        // Revoking the refresh token also invalidates its access tokens.
        $token = $account->refresh_token ?: $account->access_token;
        if (! $token) {
            return false;
        }

        return Http::asForm()->timeout(10)->post(self::GOOGLE_REVOKE_URL, ['token' => $token])->successful();
    }

    private function tiktok(SocialAccount $account): bool
    {
        if (! $account->access_token) {
            return false;
        }

        return Http::asForm()->timeout(10)->post(self::TIKTOK_REVOKE_URL, [
            'client_key' => config('services.tiktok.client_id'),
            'client_secret' => config('services.tiktok.client_secret'),
            'token' => $account->access_token,
        ])->successful();
    }

    /** DELETE /{app-scoped-user-id}/permissions with the app access token. */
    private function meta(SocialAccount $account): bool
    {
        $appToken = config('services.facebook.client_id').'|'.config('services.facebook.client_secret');
        $userId = data_get($account->meta, 'fb_user_id');

        if (! $userId && $account->access_token) {
            $debug = Http::timeout(10)->get(MetaConnector::graph('debug_token'), [
                'input_token' => $account->access_token,
                'access_token' => $appToken,
            ]);
            $userId = $debug->successful() ? $debug->json('data.user_id') : null;
        }

        if (! $userId) {
            return false;
        }

        $resp = Http::timeout(10)->delete(MetaConnector::graph($userId.'/permissions').'?'.http_build_query(['access_token' => $appToken]));

        return $resp->successful() && ($resp->json('success') === true || $resp->json() === true);
    }
}
