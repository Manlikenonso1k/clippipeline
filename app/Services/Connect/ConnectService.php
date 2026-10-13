<?php

namespace App\Services\Connect;

use App\Models\User;
use App\Services\Connect\Providers\Connector;
use App\Services\Connect\Providers\MetaConnector;
use App\Services\Connect\Providers\TikTokConnector;
use App\Services\Connect\Providers\YouTubeConnector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shared OAuth connect flow for web (Filament) and mobile. Both start here and
 * both land on the production callback /integrations/callback/{segment}.
 */
class ConnectService
{
    public const PROVIDERS = ['youtube', 'meta', 'tiktok'];

    public const PLATFORMS = ['web', 'mobile'];

    /** Callback URL segment => connect provider. */
    public const CALLBACK_SEGMENTS = ['youtube' => 'youtube', 'instagram' => 'meta', 'tiktok' => 'tiktok'];

    public function __construct(private OAuthState $states) {}

    /** Accepts legacy aliases (instagram/facebook => meta). */
    public static function normalize(string $provider): ?string
    {
        $provider = strtolower($provider);
        $provider = in_array($provider, ['instagram', 'facebook'], true) ? 'meta' : $provider;

        return in_array($provider, self::PROVIDERS, true) ? $provider : null;
    }

    public function connector(string $provider): Connector
    {
        return match ($provider) {
            'youtube' => app(YouTubeConnector::class),
            'meta' => app(MetaConnector::class),
            'tiktok' => app(TikTokConnector::class),
        };
    }

    /**
     * @return array{provider: string, platform: string, auth_url: string, expires_at: string}
     */
    public function start(User $user, string $provider, string $platform): array
    {
        $connector = $this->connector($provider);
        $verifier = $connector->usesPkce() ? Str::random(64) : null;
        $issued = $this->states->issue($user->id, $provider, $platform, $verifier);

        return [
            'provider' => $provider,
            'platform' => $platform,
            'auth_url' => $connector->authUrl($issued['state'], $verifier),
            'expires_at' => $issued['expires_at']->toIso8601String(),
        ];
    }

    /**
     * Run the callback. Never throws; the controller turns the result into a redirect.
     *
     * @return array{provider: string, platform: string, ok: bool, message: string}
     */
    public function handleCallback(Request $request, string $segment): array
    {
        $provider = self::CALLBACK_SEGMENTS[$segment];
        $rawState = $request->query('state');
        $payload = null;

        if (is_string($rawState) && $rawState !== '') {
            $payload = $this->states->decode($rawState);
            if (! $payload || $payload['provider'] !== $provider) {
                return $this->result($provider, $this->guessPlatform(), false, 'This connect link is invalid or has expired. Please try again.');
            }
        }

        $platform = $payload['platform'] ?? 'web';

        $user = $payload ? User::find($payload['uid']) : Auth::user();
        if (! $user) {
            return $this->result($provider, $platform, false, 'You must be logged in to connect integrations.');
        }

        if ($error = $request->query('error')) {
            $message = $request->query('error_description') ?: $request->query('error_message') ?: $error;

            return $this->result($provider, $platform, false, 'Authorization was cancelled or denied: '.Str::limit((string) $message, 200));
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '') {
            return $this->result($provider, $platform, false, 'The provider did not return an authorization code.');
        }

        $connector = $this->connector($provider);

        try {
            $connector->connect($user, $code, $payload['pkce'] ?? null);
        } catch (ConnectException $e) {
            return $this->result($provider, $platform, false, $e->getMessage());
        } catch (Throwable $e) {
            Log::error("Connect {$provider} callback failed: ".$e->getMessage());

            return $this->result($provider, $platform, false, 'Could not connect your account. Please try again.');
        }

        $warning = $connector instanceof MetaConnector ? $connector->warning : null;

        return $this->result($provider, $platform, true, $warning ?? self::label($provider).' connected');
    }

    public static function label(string $provider): string
    {
        return match ($provider) {
            'youtube' => 'YouTube',
            'meta' => 'Facebook & Instagram',
            'tiktok' => 'TikTok',
            default => ucfirst($provider),
        };
    }

    /** An undecodable state cannot tell us the platform: web if a session exists, else mobile. */
    private function guessPlatform(): string
    {
        return Auth::check() ? 'web' : 'mobile';
    }

    private function result(string $provider, string $platform, bool $ok, string $message): array
    {
        return compact('provider', 'platform', 'ok', 'message');
    }
}
