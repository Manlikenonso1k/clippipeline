<?php

namespace App\Jobs;

use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Services\FacebookService;
use App\Services\PublishException;

/** Facebook Page Reels: start -> hosted upload (file_url) -> wait for upload -> finish -> wait for publish. */
class PublishToFacebookReelsJob extends PublishJob
{
    protected int $pollDelay = 30;

    protected function platform(): string
    {
        return 'facebook';
    }

    protected function publish(PostDistribution $distribution, SocialAccount $account, string $token): ?PublishResult
    {
        $post = $distribution->post;
        $facebook = app(FacebookService::class);
        $pageId = $facebook->pageIdFor($account);
        $meta = (array) $distribution->meta;

        $videoId = $meta['fb_video_id'] ?? null;
        if (! $videoId) {
            $videoId = $facebook->startReel($pageId, $token);
            $this->remember($distribution, ['fb_video_id' => $videoId, 'polls' => 0]);
        }

        if (empty($meta['fb_uploaded'])) {
            $facebook->uploadHosted($videoId, $this->publicVideoUrl($post), $token);
            $this->remember($distribution, ['fb_uploaded' => true], PostDistribution::STATUS_PROCESSING);
        }

        while (true) {
            $status = $facebook->status($videoId, $token);
            $videoStatus = $status['video_status'] ?? null;

            if (in_array($videoStatus, ['error', 'upload_failed', 'expired'], true)) {
                $this->forget($distribution, ['fb_video_id', 'fb_uploaded', 'fb_finished', 'polls']);
                $detail = $status['processing_phase']['error']['message']
                    ?? $status['uploading_phase']['error']['message']
                    ?? $videoStatus;
                throw PublishException::permanent('Facebook could not process the Reel: '.$detail);
            }

            $publishStatus = $status['publishing_phase']['publish_status'] ?? null;
            if ($publishStatus === 'error') {
                throw PublishException::permanent('Facebook could not publish the Reel: '.($status['publishing_phase']['error']['message'] ?? 'publish error'));
            }

            $uploaded = ($status['uploading_phase']['status'] ?? null) === 'complete'
                || in_array($videoStatus, ['upload_complete', 'processing', 'ready'], true);

            if ($uploaded && empty(((array) $distribution->meta)['fb_finished'])) {
                $facebook->finishReel($pageId, $videoId, mb_substr($this->caption($post, $distribution), 0, 2200), $token);
                $this->remember($distribution, ['fb_finished' => true]);
            }

            if ($publishStatus === 'published' || ($videoStatus === 'ready' && ! empty(((array) $distribution->meta)['fb_finished']))) {
                break;
            }

            if ($this->pollAgain($distribution, $this->pollDelay)) {
                return null;
            }
        }

        $info = $facebook->videoInfo($videoId, $token);
        if ($info['post_id']) {
            $this->remember($distribution, ['fb_post_id' => $info['post_id']]);
        }

        // external_id is the VIDEO id: analytics reads /{video-id}/video_insights.

        return new PublishResult(
            externalId: $videoId,
            url: $info['permalink'] ?? 'https://www.facebook.com/reel/'.$videoId,
            thumbnail: $info['picture'],
        );
    }
}
