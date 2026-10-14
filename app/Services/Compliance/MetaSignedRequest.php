<?php

namespace App\Services\Compliance;

/**
 * Parses Meta's `signed_request` (`<base64url sig>.<base64url payload>`), verifying
 * the HMAC-SHA256 of the encoded payload with the app secret in constant time.
 * Never log the raw value (COMPLIANCE A-06).
 */
class MetaSignedRequest
{
    /** @return array<string, mixed>|null payload, or null when malformed / bad signature */
    public static function parse(?string $signedRequest, ?string $secret = null): ?array
    {
        $secret ??= (string) config('services.facebook.client_secret');
        if (! is_string($signedRequest) || $secret === '' || substr_count($signedRequest, '.') !== 1) {
            return null;
        }

        [$encodedSig, $encodedPayload] = explode('.', $signedRequest, 2);
        $sig = self::base64UrlDecode($encodedSig);
        $json = self::base64UrlDecode($encodedPayload);
        if ($sig === null || $json === null) {
            return null;
        }

        $payload = json_decode($json, true);
        if (! is_array($payload) || strtoupper((string) ($payload['algorithm'] ?? '')) !== 'HMAC-SHA256') {
            return null;
        }

        $expected = hash_hmac('sha256', $encodedPayload, $secret, true);

        return hash_equals($expected, $sig) ? $payload : null;
    }

    /** Build a signed_request (tests). */
    public static function make(array $payload, string $secret): string
    {
        $encoded = self::base64UrlEncode(json_encode($payload + ['algorithm' => 'HMAC-SHA256']));

        return self::base64UrlEncode(hash_hmac('sha256', $encoded, $secret, true)).'.'.$encoded;
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
