<?php

namespace App\Services\Connect;

use App\Exceptions\NeedsReauthException;
use App\Models\SocialAccount;

/**
 * The one place publish jobs and analytics fetchers get a usable access token.
 * Owner: CONNECT agent (implements refresh per provider). Callers rely only on
 * this signature.
 */
class TokenManager
{
    /**
     * Return a non-expired access token for the account, refreshing it if needed.
     *
     * @throws NeedsReauthException when refresh fails (account is marked needs_reauth).
     */
    public function freshAccessToken(SocialAccount $account): string
    {
        if (empty($account->access_token)) {
            throw new NeedsReauthException("No access token for {$account->provider} account {$account->id}.");
        }

        return $account->access_token;
    }
}
