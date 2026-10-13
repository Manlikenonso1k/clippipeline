<?php

namespace App\Services\Connect\Providers;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

abstract class BaseConnector implements Connector
{
    public function redirectUri(): string
    {
        return route('integrations.callback', ['provider' => $this->callbackSegment()]);
    }

    public function usesPkce(): bool
    {
        return false;
    }

    /**
     * Create/update the user's row for a platform and mark it healthy.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function upsert(User $user, string $platform, array $attributes): SocialAccount
    {
        $account = SocialAccount::firstOrNew(['user_id' => $user->id, 'provider' => $platform]);

        $meta = array_merge((array) $account->meta, (array) ($attributes['meta'] ?? []));
        unset($attributes['meta']);

        $account->forceFill(array_merge([
            'platform_name' => $platform,
            'status' => SocialAccount::STATUS_ACTIVE,
            'last_error' => null,
            'needs_attention' => false,
            'last_synced_at' => now(),
        ], $attributes, ['meta' => $meta ?: null]))->save();

        return $account;
    }

    /** Short, user-safe description of a failed provider response. */
    protected function describe(Response $response): string
    {
        $json = $response->json();
        $message = data_get($json, 'error.message')
            ?? data_get($json, 'error_description')
            ?? (is_string(data_get($json, 'error')) ? data_get($json, 'error') : null)
            ?? 'HTTP '.$response->status();

        return Str::limit((string) $message, 250);
    }

    /** 4xx = the grant itself is bad; 5xx/429 = try again later. */
    protected function isDefinitive(Response $response): bool
    {
        return $response->clientError() && $response->status() !== 429;
    }
}
