<?php

namespace App\Jobs;

use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Services\PublishException;
use App\Services\TikTokPublishService;

/**
 * TikTok Direct Post: creator_info -> init (FILE_UPLOAD by default; PULL_FROM_URL
 * needs a verified domain) -> chunked PUT -> poll status/fetch.
 */
class PublishToTikTokJob extends PublishJob
{
    protected int $pollDelay = 30;

    protected function platform(): string
    {
        return 'tiktok';
    }

    protected function publish(PostDistribution $distribution, SocialAccount $account, string $token): ?PublishResult
    {
        $post = $distribution->post;
        $tiktok = app(TikTokPublishService::class);

        $publishId = ((array) $distribution->meta)['tiktok_publish_id'] ?? null;
        if (! $publishId) {
            $privacy = $tiktok->choosePrivacy($tiktok->creatorInfo($token));

            $title = trim($this->title($post, $distribution)."\n".$this->caption($post, $distribution));
            $postInfo = [
                'title' => mb_substr($title, 0, 2200),
                'privacy_level' => $privacy,
                'disable_duet' => false,
                'disable_comment' => false,
                'disable_stitch' => false,
            ];

            $pull = strtoupper((string) config('services.tiktok_posting.source', 'FILE_UPLOAD')) === 'PULL_FROM_URL';
            $filePath = $pull ? null : $this->localVideoPath($post);
            $init = $tiktok->initDirectPost($token, $postInfo, $filePath, $pull ? $this->publicVideoUrl($post) : null);

            $distribution->privacy = $privacy;
            $this->remember($distribution, ['tiktok_publish_id' => $init['publish_id'], 'polls' => 0]);
            $publishId = $init['publish_id'];

            if ($filePath !== null) {
                if (! $init['upload_url']) {
                    throw PublishException::retryable('TikTok did not return an upload URL.');
                }
                $tiktok->uploadFile($init['upload_url'], $filePath);
            }
            $this->remember($distribution, [], PostDistribution::STATUS_PROCESSING);
        }

        while (true) {
            $status = $tiktok->status($token, $publishId);
            $state = $status['status'] ?? null;

            if ($state === 'PUBLISH_COMPLETE') {
                break;
            }

            if ($state === 'FAILED') {
                $this->forget($distribution, ['tiktok_publish_id', 'polls']);
                throw PublishException::permanent('TikTok could not publish the video: '.($status['fail_reason'] ?? 'unknown reason'));
            }

            if ($this->pollAgain($distribution, $this->pollDelay)) {
                return null;
            }
        }

        // Public post id only exists for public, moderated posts (the API spells it "publicaly").
        $postId = isset($status['publicaly_available_post_id'][0]) ? (string) $status['publicaly_available_post_id'][0] : null;
        $info = $postId ? $tiktok->videoInfo($token, $postId) : ['cover_image_url' => null, 'share_url' => null];

        $url = $info['share_url'];
        if (! $url && $postId && $account->handle) {
            $url = 'https://www.tiktok.com/@'.ltrim($account->handle, '@').'/video/'.$postId;
        } elseif (! $url && $account->handle) {
            $url = 'https://www.tiktok.com/@'.ltrim($account->handle, '@'); // private post: profile link
        }

        return new PublishResult(
            externalId: $postId ?? $publishId,
            url: $url,
            thumbnail: $info['cover_image_url'],
        );
    }
}
