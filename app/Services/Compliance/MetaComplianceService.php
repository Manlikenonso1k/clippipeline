<?php

namespace App\Services\Compliance;

use App\Jobs\PurgeMetaDataJob;
use App\Models\DataDeletionRequest;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connect\DisconnectService;
use Illuminate\Support\Collection;

/**
 * Meta Data Deletion + Deauthorize callbacks (COMPLIANCE C-06/C-07). Meta users are
 * matched by app-scoped user id: social_accounts.meta.fb_user_id (Connect) or
 * users.facebook_id (Facebook sign-in).
 */
class MetaComplianceService
{
    public const PLATFORMS = ['facebook', 'instagram'];

    /** C-06: record the request and queue the purge. */
    public function requestDeletion(string $metaUserId): DataDeletionRequest
    {
        $request = DataDeletionRequest::open('meta', $metaUserId);
        PurgeMetaDataJob::dispatch($metaUserId, $request->id);

        return $request;
    }

    /** C-07: drop Meta tokens/rows now and stop queued Meta publishes. @return int rows removed */
    public function deauthorize(string $metaUserId): int
    {
        $removed = 0;
        foreach ($this->userIds($metaUserId) as $userId) {
            DisconnectService::purgePlatformData($userId, self::PLATFORMS);
            $removed += SocialAccount::query()->where('user_id', $userId)->whereIn('provider', self::PLATFORMS)->delete();
        }

        return $removed;
    }

    /**
     * Delete everything obtained from Meta for this Meta user: Page/IG rows (tokens,
     * follower snapshots cascade), Meta analytics, and the Facebook login link.
     *
     * @return array<string, int>
     */
    public function purge(string $metaUserId): array
    {
        $users = $this->userIds($metaUserId);
        $analytics = 0;
        $accounts = 0;

        foreach ($users as $userId) {
            $analytics += DisconnectService::purgePlatformData($userId, self::PLATFORMS);
            $accounts += SocialAccount::query()->where('user_id', $userId)->whereIn('provider', self::PLATFORMS)->delete();
        }

        User::query()->where('facebook_id', $metaUserId)->update(['facebook_id' => null]);

        return ['users' => $users->count(), 'accounts' => $accounts, 'analytics' => $analytics];
    }

    /** @return Collection<int, int> */
    private function userIds(string $metaUserId): Collection
    {
        $fromAccounts = SocialAccount::query()
            ->whereIn('provider', self::PLATFORMS)
            ->get(['id', 'user_id', 'meta'])
            ->filter(fn (SocialAccount $a) => (string) data_get($a->meta, 'fb_user_id') === $metaUserId)
            ->pluck('user_id');

        $fromLogin = User::query()->where('facebook_id', $metaUserId)->pluck('id');

        return $fromAccounts->merge($fromLogin)->unique()->values();
    }
}
