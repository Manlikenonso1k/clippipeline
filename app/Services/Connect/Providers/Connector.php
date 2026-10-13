<?php

namespace App\Services\Connect\Providers;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connect\ConnectException;
use App\Services\Connect\TokenRefreshException;

interface Connector
{
    /** Callback path segment registered on production (/integrations/callback/{segment}). */
    public function callbackSegment(): string;

    public function redirectUri(): string;

    /** Whether the authorize/token exchange uses PKCE. */
    public function usesPkce(): bool;

    public function authUrl(string $state, ?string $pkceVerifier = null): string;

    /**
     * Exchange the code, fetch the profile and persist the account row(s).
     *
     * @return array<int, SocialAccount>
     *
     * @throws ConnectException
     */
    public function connect(User $user, string $code, ?string $pkceVerifier = null): array;

    /**
     * Refresh (or validate, for non-refreshable tokens) the account's access token in place.
     *
     * @throws TokenRefreshException
     */
    public function refresh(SocialAccount $account): void;

    /** Re-fetch handle/display name/avatar/followers/external id using the stored token. */
    public function syncProfile(SocialAccount $account): void;
}
