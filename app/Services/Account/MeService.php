<?php

namespace App\Services\Account;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/** The full `Me` JSON (CONTRACT §2 + §10). Shared by the API and Filament. */
class MeService
{
    /** Analytics newer than this keeps the sync badge "active". */
    public const ACTIVE_SYNC_HOURS = 4;

    public function __construct(private PreferencesService $preferences) {}

    public function present(User $user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],
            'server_time' => now()->toIso8601String(),
            'plan' => $this->plan($user),
            'sync' => $this->sync($user),
            'preferences' => $this->preferences->get($user),
            'flags' => $this->flags(),
        ];
    }

    /** @return array{name: string, clips_used: int, clips_limit: ?int, renews_at: ?string, billing_url: string} */
    public function plan(User $user): array
    {
        $billing = (array) (((array) ($user->settings ?? []))['billing'] ?? []);
        $plans = (array) config('clippipeline.plans', []);
        $key = ($billing['status'] ?? null) === 'active' && isset($plans[$billing['plan'] ?? '']) ? $billing['plan'] : 'starter';
        $plan = $plans[$key] ?? ['name' => 'free', 'clips_limit' => 10];

        $renews = $billing['renews_at'] ?? null;

        return [
            'name' => (string) $plan['name'],
            'clips_used' => $user->posts()->where('created_at', '>=', now()->startOfMonth())->count(),
            'clips_limit' => $plan['clips_limit'] === null ? null : (int) $plan['clips_limit'],
            'renews_at' => $key !== 'starter' && $renews ? Carbon::parse($renews)->toIso8601String() : null,
            'billing_url' => $this->billingUrl(),
        ];
    }

    /** @return array{status: string, last_synced_at: ?string} */
    public function sync(User $user): array
    {
        $accounts = SocialAccount::query()->where('user_id', $user->id)->get();
        $last = $this->lastSyncedAt($user, $accounts->pluck('id')->all());

        $attention = $accounts->contains(fn (SocialAccount $a) => $a->effectiveStatus() !== SocialAccount::STATUS_ACTIVE);
        $active = $accounts->contains(fn (SocialAccount $a) => $a->effectiveStatus() === SocialAccount::STATUS_ACTIVE)
            && $last && $last->gt(now()->subHours(self::ACTIVE_SYNC_HOURS));

        return [
            'status' => $attention ? 'attention' : ($active ? 'active' : 'idle'),
            'last_synced_at' => $last?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function flags(): array
    {
        return [
            'tiktok_audited' => (bool) config('clippipeline.tiktok_audited'),
            'youtube_audited' => (bool) config('clippipeline.youtube_audited'),
            'support_email' => (string) config('clippipeline.support_email'),
            'privacy_url' => url('/privacy'),
            'terms_url' => url('/terms'),
            'delete_account_url' => url('/delete-account'),
        ];
    }

    public function billingUrl(): string
    {
        if ($configured = config('clippipeline.billing_url')) {
            return (string) $configured;
        }

        return Route::has('billing') ? route('billing') : url('/#pricing');
    }

    /** Latest analytics write for the user: post snapshots or follower snapshots. */
    private function lastSyncedAt(User $user, array $accountIds): ?Carbon
    {
        $posts = DB::table('post_analytics')
            ->join('post_distributions', 'post_distributions.id', '=', 'post_analytics.distribution_id')
            ->join('posts', 'posts.id', '=', 'post_distributions.post_id')
            ->where('posts.user_id', $user->id)
            ->max('post_analytics.updated_at');

        $followers = $accountIds === [] ? null : DB::table('account_follower_snapshots')
            ->whereIn('social_account_id', $accountIds)
            ->max('updated_at');

        $values = array_filter([$posts, $followers]);

        return $values ? Carbon::parse(max($values)) : null;
    }
}
