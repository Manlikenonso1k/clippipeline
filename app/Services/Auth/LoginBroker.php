<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Connect\OAuthState;
use App\Services\Connect\Providers\MetaConnector;
use App\Services\Connect\Providers\TikTokConnector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Mobile sign-in broker (CONTRACT §9). The OAuth round trip runs through the
 * redirect URIs already registered for the web app; the app gets a one-time
 * code (5 min, single use) on clippipelinemobile://auth and swaps it for a
 * Sanctum token at POST /api/auth/exchange.
 */
class LoginBroker
{
    public const PROVIDERS = ['google', 'facebook', 'tiktok'];

    public const GOOGLE_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    public const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const GOOGLE_USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function __construct(private OAuthState $states, private SocialUserResolver $users) {}

    /** @return array{provider: string, auth_url: string, expires_at: string} */
    public function start(string $provider): array
    {
        $issued = $this->states->issueLogin($provider);
        $state = $issued['state'];

        $url = match ($provider) {
            'google' => self::GOOGLE_AUTH_URL.'?'.http_build_query([
                'client_id' => config('services.google.client_id'),
                'redirect_uri' => $this->googleRedirect(),
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'prompt' => 'select_account',
                'state' => $state,
            ]),
            'facebook' => 'https://www.facebook.com/'.config('services.meta.graph_version').'/dialog/oauth?'.http_build_query([
                'client_id' => config('services.facebook.client_id'),
                'redirect_uri' => app(MetaConnector::class)->redirectUri(),
                'response_type' => 'code',
                'scope' => 'public_profile,email',
                'state' => $state,
            ]),
            'tiktok' => TikTokConnector::AUTH_URL.'?'.http_build_query([
                'client_key' => config('services.tiktok.client_id'),
                'scope' => 'user.info.basic',
                'response_type' => 'code',
                'redirect_uri' => app(TikTokConnector::class)->redirectUri(),
                'state' => $state,
            ]),
        };

        return [
            'provider' => $provider,
            'auth_url' => $url,
            'expires_at' => $issued['expires_at']->toIso8601String(),
        ];
    }

    /** Whether this callback request belongs to the broker (state.purpose=login). */
    public function owns(Request $request): bool
    {
        return $this->states->isLogin($request->query('state'));
    }

    /**
     * Finish the provider callback and return the deep link to redirect to.
     */
    public function complete(Request $request, string $provider): string
    {
        $payload = $this->states->decodeLogin($request->query('state'));
        if (! $payload || $payload['provider'] !== $provider) {
            return $this->failure('This sign-in link is invalid or has expired. Please try again.');
        }

        if ($error = $request->query('error')) {
            $message = $request->query('error_description') ?: $request->query('error_message') ?: $error;

            return $this->failure('Sign-in was cancelled: '.Str::limit((string) $message, 200));
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '') {
            return $this->failure('The provider did not return an authorization code.');
        }

        try {
            $user = match ($provider) {
                'google' => $this->google($code),
                'facebook' => $this->facebook($code),
                'tiktok' => $this->tiktok($code),
            };
        } catch (Throwable $e) {
            Log::warning('auth.broker_failed', ['provider' => $provider, 'error' => $e->getMessage()]);

            return $this->failure('Could not sign you in with '.ucfirst($provider).'. Please try again.');
        }

        return $this->deepLink(['status' => 'ok', 'code' => $this->issueCode($user)]);
    }

    /** One-time code => user id, 5 minutes. */
    public function issueCode(User $user): string
    {
        $code = Str::random(48);
        Cache::put($this->cacheKey($code), $user->id, (int) config('clippipeline.login_code_ttl_seconds', 300));

        return $code;
    }

    /** Redeem a code (single use). Null when invalid, expired or already used. */
    public function redeem(string $code): ?User
    {
        $key = $this->cacheKey($code);
        $lock = Cache::lock($key.':lock', 10);

        if (! $lock->get()) {
            return null;
        }

        try {
            $userId = Cache::pull($key);
        } finally {
            $lock->release();
        }

        return $userId ? User::find($userId) : null;
    }

    private function google(string $code): User
    {
        $token = Http::asForm()->post(self::GOOGLE_TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->googleRedirect(),
        ]);
        if (! $token->successful() || ! $token->json('access_token')) {
            throw new RuntimeException('Google token exchange failed (HTTP '.$token->status().').');
        }

        $info = Http::withToken((string) $token->json('access_token'))->get(self::GOOGLE_USERINFO_URL);
        $id = $info->json('sub');
        $email = $info->json('email');
        if (! $info->successful() || ! $id || ! $email) {
            throw new RuntimeException('Google did not return a usable account.');
        }

        return $this->users->google((string) $id, (string) $email, $info->json('name'), $info->json('picture'));
    }

    private function facebook(string $code): User
    {
        $token = Http::get(MetaConnector::graph('oauth/access_token'), [
            'client_id' => config('services.facebook.client_id'),
            'client_secret' => config('services.facebook.client_secret'),
            'redirect_uri' => app(MetaConnector::class)->redirectUri(),
            'code' => $code,
        ]);
        if (! $token->successful() || ! $token->json('access_token')) {
            throw new RuntimeException('Facebook token exchange failed (HTTP '.$token->status().').');
        }

        $me = Http::get(MetaConnector::graph('me'), [
            'fields' => 'id,name,email,picture.type(large)',
            'access_token' => (string) $token->json('access_token'),
        ]);
        if (! $me->successful() || ! $me->json('id')) {
            throw new RuntimeException('Facebook did not return a usable account.');
        }

        return $this->users->facebook((string) $me->json('id'), $me->json('email'), $me->json('name'), $me->json('picture.data.url'));
    }

    private function tiktok(string $code): User
    {
        $connector = app(TikTokConnector::class);
        $token = Http::asForm()->post(TikTokConnector::TOKEN_URL, [
            'client_key' => config('services.tiktok.client_id'),
            'client_secret' => config('services.tiktok.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $connector->redirectUri(),
        ]);
        if (! $token->successful() || ! $token->json('access_token')) {
            throw new RuntimeException('TikTok token exchange failed (HTTP '.$token->status().').');
        }

        $info = Http::withToken((string) $token->json('access_token'))
            ->get(TikTokConnector::USER_URL, ['fields' => 'open_id,avatar_url,display_name']);
        $user = (array) $info->json('data.user', []);
        $openId = $user['open_id'] ?? $token->json('open_id');
        if (! $openId) {
            throw new RuntimeException('TikTok did not return a usable account.');
        }

        return $this->users->tiktok((string) $openId, $user['display_name'] ?? null, $user['avatar_url'] ?? null);
    }

    private function googleRedirect(): string
    {
        return config('services.google.redirect') ?: route('auth.callback', ['provider' => 'google']);
    }

    private function failure(string $message): string
    {
        return $this->deepLink(['status' => 'error', 'message' => $message]);
    }

    private function deepLink(array $query): string
    {
        $base = (string) config('clippipeline.mobile_auth_redirect', 'clippipelinemobile://auth');

        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function cacheKey(string $code): string
    {
        return 'auth:login_code:'.hash('sha256', $code);
    }
}
