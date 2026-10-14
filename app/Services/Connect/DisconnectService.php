<?php

namespace App\Services\Connect;

use App\Models\PostAnalytic;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;

/**
 * Removes a user's connected account (docs/CONTRACT.md §3 DELETE /social-accounts/{id}).
 * Shared by the API and the Filament Connected Accounts page. Revokes the grant at the
 * platform first (A-01), then deletes the rows and that platform's analytics (purge).
 */
class DisconnectService
{
    public function __construct(private RevokeService $revoke) {}

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

        $rows = $query->orderBy('id')->get();

        // Meta rows share one grant: revoke once.
        $this->revoke->revoke($rows->first(fn ($r) => $r->provider === 'facebook') ?? $account);

        self::purgePlatformData($user->id, $rows->pluck('provider')->all());

        $ids = $rows->pluck('id')->all();
        SocialAccount::whereIn('id', $ids)->delete();

        return $ids;
    }

    /** Disconnect by provider name (web route). @return array<int, int> deleted ids */
    public function disconnectProvider(User $user, string $provider): array
    {
        $normalized = ConnectService::normalize($provider);
        $platform = $normalized === 'meta' ? 'facebook' : $provider;

        $account = SocialAccount::where('user_id', $user->id)->where('provider', $platform)->first()
            ?? ($normalized === 'meta' ? SocialAccount::where('user_id', $user->id)->where('provider', 'instagram')->first() : null);

        return $account ? (array) $this->disconnect($user, $account->id) : [];
    }

    /**
     * Delete analytics obtained from the platforms and stop queued publishes to them.
     *
     * @param  array<int, string>  $platforms
     * @return int analytics rows deleted
     */
    public static function purgePlatformData(int $userId, array $platforms): int
    {
        $distributionIds = PostDistribution::query()
            ->whereIn('platform', $platforms)
            ->whereHas('post', fn ($q) => $q->where('user_id', $userId))
            ->pluck('id');

        PostDistribution::query()
            ->whereIn('id', $distributionIds)
            ->whereIn('status', [PostDistribution::STATUS_QUEUED, 'pending'])
            ->get()
            ->each(fn (PostDistribution $d) => $d->forceFill([
                'status' => PostDistribution::STATUS_FAILED,
                'error' => 'Account disconnected before publishing.',
                'error_message' => 'Account disconnected before publishing.',
            ])->saveQuietly());

        return PostAnalytic::query()->whereIn('distribution_id', $distributionIds)->delete();
    }
}
