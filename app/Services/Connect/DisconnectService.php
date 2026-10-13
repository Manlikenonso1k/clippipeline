<?php

namespace App\Services\Connect;

use App\Models\SocialAccount;
use App\Models\User;

/**
 * Removes a user's connected account (docs/CONTRACT.md §3 DELETE /social-accounts/{id}).
 * Shared by the API and the Filament Connected Accounts page.
 */
class DisconnectService
{
    /**
     * Delete the account; a facebook/instagram row removes both Meta rows (one shared token).
     *
     * @return array<int, int>|null deleted ids, or null when the account is not the user's
     */
    public function disconnect(User $user, int $accountId): ?array
    {
        $account = SocialAccount::where('user_id', $user->id)->find($accountId);
        if (! $account) {
            return null;
        }

        $query = SocialAccount::where('user_id', $user->id);
        $query = $account->connectProvider() === 'meta'
            ? $query->whereIn('provider', ['facebook', 'instagram'])
            : $query->whereKey($account->id);

        $ids = $query->orderBy('id')->pluck('id')->all();
        SocialAccount::whereIn('id', $ids)->delete();

        return $ids;
    }
}
