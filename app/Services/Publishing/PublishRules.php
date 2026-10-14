<?php

namespace App\Services\Publishing;

use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connect\Providers\MetaConnector;
use App\Services\Connect\TokenManager;
use App\Services\Media\Mp4Duration;
use App\Services\TikTok\CreatorInfoService;
use App\Services\TikTok\NoTikTokAccountException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Server-side publish rules (CONTRACT §12, COMPLIANCE P-02/P-03/P-04/T-09).
 * Called by PostUploadService::create() for both the API and Filament.
 */
class PublishRules
{
    public const YOUTUBE_PRIVACY = ['public', 'private', 'unlisted'];

    public const TIKTOK_FLAGS = ['disable_comment', 'disable_duet', 'disable_stitch', 'brand_organic_toggle', 'brand_content_toggle', 'is_aigc'];

    public const MB = 1048576;

    public function __construct(private CreatorInfoService $creatorInfo, private TokenManager $tokens) {}

    /**
     * Validate options + caps. Returns the per-platform options to store on each
     * distribution, and the video duration (MP4 header first, client value as fallback).
     *
     * @param  array<int, string>  $platforms
     * @param  array<string, mixed>  $platformOptions  raw platform_options input
     * @return array{options: array<string, array<string, mixed>>, duration: ?float}
     *
     * @throws ValidationException
     */
    public function validate(User $user, UploadedFile $video, array $platforms, array $platformOptions, ?float $clientDuration, ?string $caption = null, string $hashtagLine = ''): array
    {
        $platforms = array_values(array_unique($platforms));
        $rules = [];

        if (in_array('youtube', $platforms, true)) {
            $rules['platform_options.youtube.privacy_status'] = ['required', Rule::in(self::YOUTUBE_PRIVACY)];
            $rules['platform_options.youtube.guidelines_confirmed'] = ['accepted'];
        }
        if (in_array('tiktok', $platforms, true)) {
            $rules['platform_options.tiktok.privacy_level'] = ['required', 'string'];
            foreach (self::TIKTOK_FLAGS as $flag) {
                $rules["platform_options.tiktok.{$flag}"] = ['nullable', 'boolean'];
            }
            $rules['platform_options.tiktok.music_consent'] = ['accepted'];
        }

        Validator::make(['platform_options' => $platformOptions], $rules, [
            'platform_options.youtube.guidelines_confirmed.accepted' => "Confirm the video follows YouTube's Community Guidelines.",
            'platform_options.tiktok.music_consent.accepted' => "You must agree to TikTok's Music Usage Confirmation.",
        ])->validate();

        $headerDuration = Mp4Duration::seconds((string) $video->getRealPath());
        $duration = $headerDuration ?? ($clientDuration !== null && $clientDuration > 0 ? $clientDuration : null);
        $sizeMb = (int) $video->getSize() / self::MB;
        $errors = [];
        $out = [];

        foreach ($platforms as $platform) {
            $raw = (array) ($platformOptions[$platform] ?? []);

            switch ($platform) {
                case 'youtube':
                    $privacy = (string) $raw['privacy_status'];
                    $out['youtube'] = [
                        // Forced private until the YouTube API audit passes (P-04).
                        'privacy_status' => config('clippipeline.youtube_audited') ? $privacy : 'private',
                        'requested_privacy_status' => $privacy,
                        'guidelines_confirmed' => true,
                    ];
                    $description = trim(str_replace(['<', '>'], '', (string) $caption)."\n\n".$hashtagLine);
                    if (strlen($description) > 5000) {
                        $errors['caption'][] = 'YouTube descriptions are limited to 5000 bytes (caption + hashtags).';
                    }
                    break;

                case 'facebook':
                    if ($duration !== null && ($duration < 3 || $duration > 90)) {
                        $errors['video'][] = 'Facebook Reels must be 3 to 90 seconds long.';
                    }
                    if ($this->recentCount($user, 'facebook') >= (int) config('clippipeline.caps.facebook_reels_per_day', 30)) {
                        $errors['video'][] = 'Facebook limit reached: 30 Reels per Page in 24 hours. Try again later.';
                    }
                    $out['facebook'] = [];
                    break;

                case 'instagram':
                    if ($duration !== null && ($duration < 3 || $duration > 900)) {
                        $errors['video'][] = 'Instagram Reels must be 3 seconds to 15 minutes long.';
                    }
                    if ($sizeMb > 300) {
                        $errors['video'][] = 'Instagram Reels must be 300 MB or smaller.';
                    }
                    if ($this->instagramLimitReached($user)) {
                        $errors['video'][] = 'Instagram limit reached: 100 posts in 24 hours. Try again later.';
                    }
                    $out['instagram'] = [];
                    break;

                case 'tiktok':
                    [$tiktok, $tiktokErrors] = $this->tiktok($user, $raw, $duration, $sizeMb);
                    $out['tiktok'] = $tiktok;
                    $errors = array_merge_recursive($errors, $tiktokErrors);
                    break;
            }
        }

        if ($errors) {
            throw ValidationException::withMessages(array_map(fn ($m) => array_values(array_unique($m)), $errors));
        }

        return ['options' => $out, 'duration' => $duration];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, array<int, string>>} */
    private function tiktok(User $user, array $raw, ?float $duration, float $sizeMb): array
    {
        $errors = [];
        $key = 'platform_options.tiktok.privacy_level';

        try {
            $info = $this->creatorInfo->lastForUser($user);
        } catch (NoTikTokAccountException) {
            return [[], ['platforms' => ['Connect an active tiktok account first.']]];
        }

        $privacy = (string) $raw['privacy_level'];
        $brandContent = (bool) ($raw['brand_content_toggle'] ?? false);

        if (! $info['can_post']) {
            $errors['platforms'][] = $info['reason'] ?: 'TikTok does not allow this account to post right now.';
        }
        if (! in_array($privacy, (array) $info['privacy_level_options'], true)) {
            $errors[$key][] = 'Choose one of the privacy options TikTok offers for this account.';
        }
        if ($brandContent && $privacy === 'SELF_ONLY') {
            $errors[$key][] = 'Branded content visibility cannot be set to private.';
        }
        $max = (int) ($info['max_video_post_duration_sec'] ?? 0);
        if ($duration !== null && $max > 0 && $duration > $max) {
            $errors['video'][] = "This TikTok account can post videos up to {$max} seconds.";
        }
        if ($sizeMb > 4096) {
            $errors['video'][] = 'TikTok videos must be 4 GB or smaller.';
        }

        // Comment/Duet/Stitch default off (T-03) and stay off when the creator disabled them.
        $off = fn (string $k, bool $creatorOff) => $creatorOff || filter_var($raw[$k] ?? true, FILTER_VALIDATE_BOOL);

        return [[
            'privacy_level' => $privacy,
            'disable_comment' => $off('disable_comment', (bool) $info['comment_disabled']),
            'disable_duet' => $off('disable_duet', (bool) $info['duet_disabled']),
            'disable_stitch' => $off('disable_stitch', (bool) $info['stitch_disabled']),
            'brand_organic_toggle' => filter_var($raw['brand_organic_toggle'] ?? false, FILTER_VALIDATE_BOOL),
            'brand_content_toggle' => $brandContent,
            'is_aigc' => filter_var($raw['is_aigc'] ?? false, FILTER_VALIDATE_BOOL),
            'music_consent' => true,
        ], $errors];
    }

    /** Distributions to a platform created by this user in the last 24h (not failed). */
    private function recentCount(User $user, string $platform): int
    {
        return PostDistribution::query()
            ->where('platform', $platform)
            ->where('status', '!=', PostDistribution::STATUS_FAILED)
            ->where('created_at', '>=', now()->subDay())
            ->whereHas('post', fn ($q) => $q->where('user_id', $user->id))
            ->count();
    }

    /** IG cap from GET /{ig-user-id}/content_publishing_limit, local count as fallback. */
    private function instagramLimitReached(User $user): bool
    {
        $cap = (int) config('clippipeline.caps.instagram_posts_per_day', 100);
        $account = SocialAccount::query()->where('user_id', $user->id)->where('provider', 'instagram')->first();

        if ($account && $account->external_id) {
            try {
                $token = $this->tokens->freshAccessToken($account);
                $resp = Http::timeout(10)->get(MetaConnector::graph($account->external_id.'/content_publishing_limit'), [
                    'fields' => 'quota_usage,config',
                    'access_token' => $token,
                ]);
                if ($resp->successful() && is_array($resp->json('data.0'))) {
                    $usage = (int) $resp->json('data.0.quota_usage', 0);
                    $total = (int) ($resp->json('data.0.config.quota_total') ?: $cap);

                    // Queued-but-unpublished posts consume quota once they publish.
                    $pending = PostDistribution::query()->where('platform', 'instagram')
                        ->whereIn('status', PostDistribution::IN_FLIGHT)
                        ->whereHas('post', fn ($q) => $q->where('user_id', $user->id))
                        ->count();

                    return $usage + $pending >= min($total, $cap);
                }
            } catch (Throwable) {
                // fall back to the local count
            }
        }

        return $this->recentCount($user, 'instagram') >= $cap;
    }
}
