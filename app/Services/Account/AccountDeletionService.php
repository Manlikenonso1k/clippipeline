<?php

namespace App\Services\Account;

use App\Jobs\PurgeUserDataJob;
use App\Models\DataDeletionRequest;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connect\DisconnectService;
use App\Services\Connect\RevokeService;
use App\Services\PostDeletionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Account deletion (COMPLIANCE A-02, CONTRACT §10 DELETE /me). Shared by the API
 * and Filament. Immediately: revoke every platform grant, delete social accounts,
 * Sanctum + device tokens. Then a queued purge removes posts, files, analytics,
 * notifications and the user (target: right away; guaranteed within 7 days by
 * the daily accounts:retention re-dispatch).
 */
class AccountDeletionService
{
    public function __construct(private RevokeService $revoke, private PostDeletionService $posts) {}

    public function delete(User $user): void
    {
        $this->requestDeletion($user);
    }

    /** @return Carbon when the purge is guaranteed to be complete */
    public function requestDeletion(User $user): Carbon
    {
        $request = DataDeletionRequest::open('account', (string) $user->id);

        $this->revokeAndDetach($user);

        $user->forceFill(['deletion_requested_at' => $user->deletion_requested_at ?? now()])->save();

        Log::info('account.deletion_requested', ['request' => $request->code]);

        PurgeUserDataJob::dispatch($user->id, $request->id);

        return now()->addDays((int) config('clippipeline.retention.deletion_days', 7));
    }

    /** Immediate part: revoke grants and drop credentials (A-02). */
    public function revokeAndDetach(User $user): void
    {
        $accounts = SocialAccount::query()->where('user_id', $user->id)->get();

        // Meta rows share one grant: revoke once (facebook row preferred).
        $revoked = [];
        foreach ($accounts->sortBy(fn ($a) => $a->provider === 'facebook' ? 0 : 1) as $account) {
            $grant = $account->connectProvider();
            if (! isset($revoked[$grant])) {
                $this->revoke->revoke($account);
                $revoked[$grant] = true;
            }
        }

        DisconnectService::purgePlatformData($user->id, $accounts->pluck('provider')->all());
        SocialAccount::query()->where('user_id', $user->id)->delete();
        $user->tokens()->delete();
        $user->deviceTokens()->delete();
    }

    /**
     * Queued part: delete everything else, then the user. Idempotent.
     *
     * @return array<string, int> counts for the PII-free audit row
     */
    public function purge(User $user): array
    {
        $this->revokeAndDetach($user);

        $posts = 0;
        Post::query()->where('user_id', $user->id)->get()->each(function (Post $post) use (&$posts) {
            $this->posts->delete($post);
            $posts++;
        });

        $notifications = $user->notifications()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->delete();

        return ['posts' => $posts, 'notifications' => $notifications];
    }
}
