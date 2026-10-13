<?php

namespace App\Services\Connect;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Encrypted + MAC-signed OAuth `state` (Laravel's encrypter is authenticated).
 * Payload: {uid, provider, platform, nonce, exp, pkce?}. Valid TTL_MINUTES.
 */
class OAuthState
{
    public const TTL_MINUTES = 10;

    /**
     * @return array{state: string, payload: array<string, mixed>, expires_at: Carbon}
     */
    public function issue(int $userId, string $provider, string $platform, ?string $pkceVerifier = null): array
    {
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        $payload = array_filter([
            'uid' => $userId,
            'provider' => $provider,
            'platform' => $platform,
            'nonce' => Str::random(32),
            'exp' => $expiresAt->getTimestamp(),
            'pkce' => $pkceVerifier,
        ], fn ($v) => $v !== null);

        return [
            'state' => Crypt::encryptString(json_encode($payload)),
            'payload' => $payload,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Decode and validate a state string. Returns null when tampered, malformed or expired.
     *
     * @return array<string, mixed>|null
     */
    public function decode(?string $state): ?array
    {
        if (! is_string($state) || $state === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($state), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($payload) || ! isset($payload['uid'], $payload['provider'], $payload['platform'], $payload['exp'])) {
            return null;
        }

        if ((int) $payload['exp'] < now()->getTimestamp()) {
            return null;
        }

        return $payload;
    }
}
