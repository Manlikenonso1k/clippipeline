<?php

namespace App\Jobs;

use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Services\InstagramService;
use App\Services\PublishException;

/** Instagram Reels: create container -> poll status_code (release) -> media_publish. */
class PublishToInstagramReelsJob extends PublishJob
{
    /** Meta recommends polling about once a minute. */
    protected int $pollDelay = 60;

    protected int $maxPolls = 10;

    protected function platform(): string
    {
        return 'instagram';
    }

    protected function publish(PostDistribution $distribution, SocialAccount $account, string $token): ?PublishResult
    {
        $post = $distribution->post;
        $instagram = app(InstagramService::class);
        $igUserId = $instagram->igUserIdFor($account);

        $containerId = ((array) $distribution->meta)['ig_container_id'] ?? null;
        if (! $containerId) {
            $containerId = $instagram->createReelContainer(
                $igUserId,
                $this->publicVideoUrl($post),
                mb_substr($this->caption($post, $distribution), 0, 2200),
                $token,
            );
            $this->remember($distribution, ['ig_container_id' => $containerId, 'polls' => 0], PostDistribution::STATUS_PROCESSING);
        }

        while (true) {
            $status = $instagram->containerStatus($containerId, $token);
            $code = $status['status_code'];

            if ($code === 'FINISHED') {
                break;
            }

            if (in_array($code, ['ERROR', 'EXPIRED'], true)) {
                // A dead container cannot be reused; a retry starts a fresh one.
                $this->forget($distribution, ['ig_container_id', 'polls']);
                throw PublishException::permanent('Instagram could not process the video: '.($status['status'] ?: $code));
            }

            if ($this->pollAgain($distribution, $this->pollDelay)) {
                return null;
            }
        }

        $mediaId = $instagram->publishContainer($igUserId, $containerId, $token);
        $info = $instagram->mediaInfo($mediaId, $token);

        return new PublishResult(
            externalId: $mediaId,
            url: $info['permalink'],
            thumbnail: $info['thumbnail_url'],
        );
    }
}
