<?php

namespace App\Services\Connect\Providers;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connect\ConnectException;
use App\Services\Connect\TokenRefreshException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * TikTok Login Kit for Web (v2). Web apps do not use PKCE (code_verifier is
 * "required for mobile and desktop app only" per /v2/oauth/token/ docs).
 */
class TikTokConnector extends BaseConnector
{
    public const AUTH_URL = 'https://www.tiktok.com/v2/auth/authorize/';

    public const TOKEN_URL = 'https://open.tiktokapis.com/v2/oauth/token/';

    public const USER_URL = 'https://open.tiktokapis.com/v2/user/info/';

    public const SCOPES = ['user.info.basic', 'video.list', 'video.publish', 'video.upload'];

    /** Fields available under user.info.basic (follower_count needs user.info.stats). */
    public const USER_FIELDS = 'open_id,union_id,avatar_url,display_name';

    public function callbackSegment(): string
    {
        return 'tiktok';
    }

    public function redirectUri(): string
    {
        return config('services.tiktok.redirect') ?: parent::redirectUri();
    }

    public function authUrl(string $state, ?string $pkceVerifier = null): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_key' => config('services.tiktok.client_id'),
            'scope' => implode(',', self::SCOPES),
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ]);
    }

    public function connect(User $user, string $code, ?string $pkceVerifier = null): array
    {
        $resp = Http::asForm()->post(self::TOKEN_URL, [
            'client_key' => config('services.tiktok.client_id'),
            'client_secret' => config('services.tiktok.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
        ]);

        if (! $resp->successful() || ! $resp->json('access_token')) {
            throw new ConnectException('TikTok token exchange failed: '.$this->describe($resp));
        }

        $token = (string) $resp->json('access_token');
        $profile = $this->fetchUser($token) ?? ['external_id' => (string) $resp->json('open_id')];

        return [$this->upsert($user, 'tiktok', array_merge($profile, [
            'access_token' => $token,
            'refresh_token' => $resp->json('refresh_token'),
            'expires_at' => now()->addSeconds((int) $resp->json('expires_in', 86400)),
            'meta' => [
                'open_id' => $resp->json('open_id'),
                'scope' => $resp->json('scope'),
                'refresh_expires_at' => now()->addSeconds((int) $resp->json('refresh_expires_in', 31536000))->toIso8601String(),
            ],
        ]))];
    }

    public function refresh(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new TokenRefreshException('TikTok account has no refresh token; reconnect it.');
        }

        try {
            $resp = Http::asForm()->post(self::TOKEN_URL, [
                'client_key' => config('services.tiktok.client_id'),
                'client_secret' => config('services.tiktok.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
            ]);
        } catch (ConnectionException $e) {
            throw new TokenRefreshException('TikTok unreachable: '.$e->getMessage(), false, $e);
        }

        // TikTok can return 200 with an `error` body.
        if (! $resp->successful() || ! $resp->json('access_token')) {
            $definitive = $resp->successful() || $this->isDefinitive($resp);

            throw new TokenRefreshException('TikTok refresh failed: '.$this->describe($resp), $definitive);
        }

        $meta = (array) $account->meta;
        $meta['refresh_expires_at'] = now()->addSeconds((int) $resp->json('refresh_expires_in', 31536000))->toIso8601String();

        $account->forceFill([
            'access_token' => (string) $resp->json('access_token'),
            // The refresh token may rotate; always keep the newest.
            'refresh_token' => $resp->json('refresh_token') ?: $account->refresh_token,
            'expires_at' => now()->addSeconds((int) $resp->json('expires_in', 86400)),
            'meta' => $meta,
        ]);
    }

    public function syncProfile(SocialAccount $account): void
    {
        $profile = $this->fetchUser((string) $account->access_token);
        if ($profile) {
            $account->forceFill($profile + ['last_synced_at' => now()]);
        }
    }

    /**
     * @return array{external_id: string, handle: ?string, display_name: ?string, avatar_url: ?string}|null
     */
    private function fetchUser(string $token): ?array
    {
        $resp = Http::withToken($token)->get(self::USER_URL, ['fields' => self::USER_FIELDS]);
        $user = $resp->successful() ? $resp->json('data.user') : null;

        if (! $user || empty($user['open_id'])) {
            return null;
        }

        return [
            'external_id' => (string) $user['open_id'],
            'handle' => $user['display_name'] ?? null,
            'display_name' => $user['display_name'] ?? null,
            'avatar_url' => $user['avatar_url'] ?? null,
        ];
    }
}
