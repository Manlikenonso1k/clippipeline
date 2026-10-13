<?php

namespace App\Services\Connect\Providers;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connect\ConnectException;
use App\Services\Connect\TokenRefreshException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Google OAuth 2.0 web-server flow for YouTube. */
class YouTubeConnector extends BaseConnector
{
    public const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const CHANNELS_URL = 'https://www.googleapis.com/youtube/v3/channels';

    public const SCOPES = [
        'https://www.googleapis.com/auth/youtube.upload',
        'https://www.googleapis.com/auth/youtube.readonly',
        'https://www.googleapis.com/auth/yt-analytics.readonly',
    ];

    public function callbackSegment(): string
    {
        return 'youtube';
    }

    public function authUrl(string $state, ?string $pkceVerifier = null): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    public function connect(User $user, string $code, ?string $pkceVerifier = null): array
    {
        $resp = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
        ]);

        if (! $resp->successful() || ! $resp->json('access_token')) {
            throw new ConnectException('Google token exchange failed: '.$this->describe($resp));
        }

        $token = (string) $resp->json('access_token');
        $profile = $this->fetchChannel($token);

        if (! $profile) {
            throw new ConnectException('No YouTube channel found for this Google account. Create a channel, then reconnect.');
        }

        $existing = SocialAccount::where('user_id', $user->id)->where('provider', 'youtube')->first();

        return [$this->upsert($user, 'youtube', array_merge($profile, [
            'access_token' => $token,
            // Google omits refresh_token on some re-consents; keep the previous one.
            'refresh_token' => $resp->json('refresh_token') ?: $existing?->refresh_token,
            'expires_at' => now()->addSeconds((int) $resp->json('expires_in', 3600)),
            'meta' => ['scope' => $resp->json('scope')],
        ]))];
    }

    public function refresh(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new TokenRefreshException('YouTube account has no refresh token; reconnect it.');
        }

        try {
            $resp = Http::asForm()->post(self::TOKEN_URL, [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $account->refresh_token,
                'grant_type' => 'refresh_token',
            ]);
        } catch (ConnectionException $e) {
            throw new TokenRefreshException('Google unreachable: '.$e->getMessage(), false, $e);
        }

        if (! $resp->successful() || ! $resp->json('access_token')) {
            throw new TokenRefreshException('Google refresh failed: '.$this->describe($resp), $this->isDefinitive($resp));
        }

        $account->forceFill([
            'access_token' => (string) $resp->json('access_token'),
            'refresh_token' => $resp->json('refresh_token') ?: $account->refresh_token,
            'expires_at' => now()->addSeconds((int) $resp->json('expires_in', 3600)),
        ]);
    }

    public function syncProfile(SocialAccount $account): void
    {
        $profile = $this->fetchChannel((string) $account->access_token);
        if ($profile) {
            $account->forceFill($profile + ['last_synced_at' => now()]);
        }
    }

    /**
     * @return array{external_id: string, handle: string, display_name: ?string, avatar_url: ?string, follower_count: int}|null
     */
    private function fetchChannel(string $token): ?array
    {
        $resp = Http::withToken($token)->get(self::CHANNELS_URL, [
            'part' => 'snippet,statistics',
            'mine' => 'true',
        ]);

        $channel = $resp->successful() ? $resp->json('items.0') : null;
        if (! $channel) {
            return null;
        }

        $title = data_get($channel, 'snippet.title');
        $custom = data_get($channel, 'snippet.customUrl');

        return [
            'external_id' => (string) $channel['id'],
            'handle' => $custom ? (str_starts_with($custom, '@') ? $custom : '@'.$custom) : (string) $title,
            'display_name' => $title,
            'avatar_url' => data_get($channel, 'snippet.thumbnails.high.url') ?? data_get($channel, 'snippet.thumbnails.default.url'),
            'follower_count' => (int) data_get($channel, 'statistics.subscriberCount', 0),
        ];
    }
}
