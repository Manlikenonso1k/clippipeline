<?php

namespace App\Services\Connect\Providers;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connect\ConnectException;
use App\Services\Connect\TokenRefreshException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Facebook Login -> long-lived user token -> Page token(s). One flow creates
 * two rows: `facebook` (Page) and `instagram` (the Page's IG business account,
 * same Page token). Long-lived Page tokens have no fixed expiry, so "refresh"
 * only validates them via /debug_token.
 */
class MetaConnector extends BaseConnector
{
    public const SCOPES = [
        'pages_show_list',
        'pages_read_engagement',
        'pages_manage_posts',
        'read_insights',
        'business_management',
        'instagram_basic',
        'instagram_content_publish',
        'instagram_manage_insights',
    ];

    public const PAGE_FIELDS = 'id,name,access_token,picture{url},followers_count,fan_count,'
        .'instagram_business_account{id,username,name,profile_picture_url,followers_count}';

    /** Set by the last connect() when the IG row could not be linked. */
    public ?string $warning = null;

    public static function graph(string $path = ''): string
    {
        return 'https://graph.facebook.com/'.config('services.meta.graph_version').'/'.ltrim($path, '/');
    }

    public function callbackSegment(): string
    {
        return 'instagram';
    }

    public function redirectUri(): string
    {
        return config('services.facebook.redirect') ?: parent::redirectUri();
    }

    public function authUrl(string $state, ?string $pkceVerifier = null): string
    {
        return 'https://www.facebook.com/'.config('services.meta.graph_version').'/dialog/oauth?'.http_build_query([
            'client_id' => config('services.facebook.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'response_type' => 'code',
            'scope' => implode(',', self::SCOPES),
        ]);
    }

    public function connect(User $user, string $code, ?string $pkceVerifier = null): array
    {
        $resp = Http::get(self::graph('oauth/access_token'), [
            'client_id' => config('services.facebook.client_id'),
            'client_secret' => config('services.facebook.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]);

        if (! $resp->successful() || ! $resp->json('access_token')) {
            throw new ConnectException('Facebook token exchange failed: '.$this->describe($resp));
        }

        return $this->storeFromUserToken($user, (string) $resp->json('access_token'));
    }

    /**
     * Exchange a user token for a long-lived one, then persist the Page + IG rows.
     *
     * @return array<int, SocialAccount>
     *
     * @throws ConnectException
     */
    public function storeFromUserToken(User $user, string $userToken): array
    {
        $this->warning = null;

        $long = Http::get(self::graph('oauth/access_token'), [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('services.facebook.client_id'),
            'client_secret' => config('services.facebook.client_secret'),
            'fb_exchange_token' => $userToken,
        ]);

        if (! $long->successful() || ! $long->json('access_token')) {
            throw new ConnectException('Could not get a long-lived Facebook token: '.$this->describe($long));
        }

        $longToken = (string) $long->json('access_token');

        $pages = Http::get(self::graph('me/accounts'), [
            'fields' => self::PAGE_FIELDS,
            'limit' => 100,
            'access_token' => $longToken,
        ]);

        if (! $pages->successful()) {
            throw new ConnectException('Could not list your Facebook Pages: '.$this->describe($pages));
        }

        $list = array_values(array_filter((array) $pages->json('data', []), fn ($p) => ! empty($p['access_token'])));

        if (! $list) {
            $message = 'No Facebook Page was shared with ClipPipeline. Reconnect and select the Page linked to your Instagram account.';

            $this->upsert($user, 'instagram', [
                'access_token' => $longToken,
                'expires_at' => $long->json('expires_in') ? now()->addSeconds((int) $long->json('expires_in')) : null,
                'external_id' => null,
                'status' => SocialAccount::STATUS_ERROR,
                'last_error' => $message,
                'needs_attention' => true,
            ]);

            throw new ConnectException($message);
        }

        $page = collect($list)->first(fn ($p) => ! empty($p['instagram_business_account']['id'])) ?? $list[0];
        $pageToken = (string) $page['access_token'];
        $pagesMeta = array_map(fn ($p) => [
            'id' => (string) $p['id'],
            'name' => $p['name'] ?? null,
            'ig_user_id' => $p['instagram_business_account']['id'] ?? null,
        ], $list);

        $facebook = $this->upsert($user, 'facebook', $this->pageProfile($page) + [
            'access_token' => $pageToken,
            'refresh_token' => null,
            'expires_at' => null,
            'meta' => [
                'page_id' => (string) $page['id'],
                'page_name' => $page['name'] ?? null,
                'login_flow' => 'facebook',
                'pages' => $pagesMeta,
                'validated_at' => now()->toIso8601String(),
            ],
        ]);

        $ig = $page['instagram_business_account'] ?? null;

        if (! empty($ig['id'])) {
            $instagram = $this->upsert($user, 'instagram', $this->igProfile($ig) + [
                'access_token' => $pageToken,
                'refresh_token' => null,
                'expires_at' => null,
                'meta' => [
                    'ig_user_id' => (string) $ig['id'],
                    'page_id' => (string) $page['id'],
                    'login_flow' => 'facebook',
                    'validated_at' => now()->toIso8601String(),
                ],
            ]);
        } else {
            $this->warning = 'Facebook Page connected, but no Instagram Business/Creator account is linked to "'
                .($page['name'] ?? 'your Page').'". Link one, then reconnect.';

            $instagram = $this->upsert($user, 'instagram', [
                'access_token' => $pageToken,
                'refresh_token' => null,
                'expires_at' => null,
                'external_id' => null,
                'status' => SocialAccount::STATUS_ERROR,
                'last_error' => $this->warning,
                'needs_attention' => true,
                'meta' => ['ig_user_id' => null, 'page_id' => (string) $page['id'], 'login_flow' => 'facebook'],
            ]);
        }

        return [$facebook, $instagram];
    }

    /** Page tokens do not refresh; confirm the token is still valid. */
    public function refresh(SocialAccount $account): void
    {
        try {
            $resp = Http::get(self::graph('debug_token'), [
                'input_token' => $account->access_token,
                'access_token' => config('services.facebook.client_id').'|'.config('services.facebook.client_secret'),
            ]);
        } catch (ConnectionException $e) {
            throw new TokenRefreshException('Facebook unreachable: '.$e->getMessage(), false, $e);
        }

        if (! $resp->successful()) {
            throw new TokenRefreshException('Facebook token check failed: '.$this->describe($resp), false);
        }

        $valid = $resp->json('data.is_valid');
        if (! is_bool($valid)) {
            throw new TokenRefreshException('Facebook token check returned an unexpected response.', false);
        }

        if (! $valid) {
            $why = $resp->json('data.error.message') ?? 'token is no longer valid';

            throw new TokenRefreshException('Facebook token invalid: '.Str::limit((string) $why, 200));
        }

        $expiresAt = (int) $resp->json('data.expires_at', 0);
        $meta = (array) $account->meta;
        $meta['validated_at'] = now()->toIso8601String();

        $account->forceFill([
            'expires_at' => $expiresAt > 0 ? now()->setTimestamp($expiresAt) : null,
            'meta' => $meta,
        ]);
    }

    public function syncProfile(SocialAccount $account): void
    {
        if (! $account->external_id) {
            return;
        }

        $fields = $account->provider === 'facebook'
            ? 'id,name,picture{url},followers_count,fan_count'
            : 'id,username,name,profile_picture_url,followers_count';

        $resp = Http::get(self::graph($account->external_id), [
            'fields' => $fields,
            'access_token' => $account->access_token,
        ]);

        if ($resp->successful() && $resp->json('id')) {
            $profile = $account->provider === 'facebook' ? $this->pageProfile($resp->json()) : $this->igProfile($resp->json());
            $account->forceFill($profile + ['last_synced_at' => now()]);
        }
    }

    /** @param  array<string, mixed>  $page */
    private function pageProfile(array $page): array
    {
        return [
            'external_id' => (string) $page['id'],
            'handle' => $page['name'] ?? null,
            'display_name' => $page['name'] ?? null,
            'avatar_url' => data_get($page, 'picture.data.url'),
            'follower_count' => (int) ($page['followers_count'] ?? $page['fan_count'] ?? 0),
        ];
    }

    /** @param  array<string, mixed>  $ig */
    private function igProfile(array $ig): array
    {
        $username = $ig['username'] ?? null;

        return [
            'external_id' => (string) $ig['id'],
            'handle' => $username ? '@'.$username : null,
            'display_name' => $ig['name'] ?? $username,
            'avatar_url' => $ig['profile_picture_url'] ?? null,
            'follower_count' => (int) ($ig['followers_count'] ?? 0),
        ];
    }
}
