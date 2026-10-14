<?php

namespace App\Services\TikTok;

use App\Exceptions\NeedsReauthException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connect\TokenManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * TikTok creator_info (CONTRACT §13, COMPLIANCE T-01/T-08): POST
 * /v2/post/publish/creator_info/query/ every time the TikTok form opens. The
 * options returned to the client are cached per user so POST /posts can enforce
 * "privacy must be in the last creator_info options" (T-09).
 */
class CreatorInfoService
{
    public const URL = 'https://open.tiktokapis.com/v2/post/publish/creator_info/query/';

    public const CACHE_TTL_SECONDS = 7200;

    /** TikTok "intentional" 200 errors that mean the creator cannot post right now. */
    public const BLOCKING_ERRORS = [
        'spam_risk_too_many_posts' => 'This TikTok account has reached its posting limit for now. Try again later.',
        'spam_risk_user_banned_from_posting' => 'TikTok has restricted this account from posting.',
        'reached_active_user_cap' => "ClipPipeline has reached TikTok's daily limit of active creators. Try again tomorrow.",
    ];

    public function __construct(private TokenManager $tokens) {}

    public static function cacheKey(int $userId): string
    {
        return 'tiktok:creator_info:'.$userId;
    }

    /** @throws NoTikTokAccountException */
    public function forUser(User $user): array
    {
        $account = SocialAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', 'tiktok')
            ->first();

        if (! $account || $account->effectiveStatus() !== SocialAccount::STATUS_ACTIVE) {
            throw new NoTikTokAccountException;
        }

        $audited = (bool) config('clippipeline.tiktok_audited');
        $info = [
            'creator_nickname' => (string) ($account->display_name ?? ''),
            'creator_username' => $account->handle ? ltrim((string) $account->handle, '@') : '',
            'creator_avatar_url' => $account->avatar_url,
            'privacy_level_options' => [],
            'comment_disabled' => false,
            'duet_disabled' => false,
            'stitch_disabled' => false,
            'max_video_post_duration_sec' => 0,
            'can_post' => false,
            'reason' => null,
            'audited' => $audited,
        ];

        try {
            $token = $this->tokens->freshAccessToken($account);
            $response = Http::withToken($token)->asJson()->timeout(15)->post(self::URL, (object) []);
        } catch (NeedsReauthException) {
            return ['reason' => 'Reconnect your TikTok account to post.'] + $info;
        } catch (Throwable) {
            return ['reason' => 'TikTok is not responding. Try again in a moment.'] + $info;
        }

        $code = (string) ($response->json('error.code') ?? ($response->successful() ? 'ok' : 'http_'.$response->status()));

        if ($code !== 'ok') {
            if (in_array($code, ['access_token_invalid', 'scope_not_authorized'], true)) {
                try {
                    $this->tokens->markNeedsReauth($account, 'TikTok rejected the access token ('.$code.').');
                } catch (NeedsReauthException) {
                    // flagged
                }
                $reason = 'Reconnect your TikTok account to post.';
            } else {
                $reason = self::BLOCKING_ERRORS[$code] ?? 'TikTok could not load your posting options. Try again later.';
            }

            return ['reason' => $reason] + $info;
        }

        $data = (array) $response->json('data', []);
        $options = array_values(array_filter((array) ($data['privacy_level_options'] ?? []), 'is_string'));
        if (! $audited) {
            // Unaudited apps may only post SELF_ONLY (T-08).
            $options = array_values(array_intersect($options ?: ['SELF_ONLY'], ['SELF_ONLY']));
        }

        $result = [
            'creator_nickname' => (string) ($data['creator_nickname'] ?? $info['creator_nickname']),
            'creator_username' => (string) ($data['creator_username'] ?? $info['creator_username']),
            'creator_avatar_url' => $data['creator_avatar_url'] ?? $info['creator_avatar_url'],
            'privacy_level_options' => $options,
            'comment_disabled' => (bool) ($data['comment_disabled'] ?? false),
            'duet_disabled' => (bool) ($data['duet_disabled'] ?? false),
            'stitch_disabled' => (bool) ($data['stitch_disabled'] ?? false),
            'max_video_post_duration_sec' => (int) ($data['max_video_post_duration_sec'] ?? 0),
            'can_post' => $options !== [],
            'reason' => $options === [] ? 'TikTok returned no privacy options for this account.' : null,
            'audited' => $audited,
        ];

        Cache::put(self::cacheKey($user->id), $result, self::CACHE_TTL_SECONDS);

        return $result;
    }

    /** Last creator_info served to this user, fetching it when none is cached. */
    public function lastForUser(User $user): array
    {
        return Cache::get(self::cacheKey($user->id)) ?? $this->forUser($user);
    }
}
