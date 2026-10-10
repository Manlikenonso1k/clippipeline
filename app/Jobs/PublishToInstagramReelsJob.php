<?php

namespace App\Jobs;

use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Services\InstagramService;
use App\Support\VideoResolver;
use RuntimeException;

class PublishToInstagramReelsJob extends PublishJob
{
    protected function provider(): string
    {
        return 'instagram';
    }

    protected function publish(PostDistribution $distribution, SocialAccount $account): string
    {
        $post = $distribution->post;
        $meta = (array) $distribution->meta;

        $caption = $meta['caption'] ?? $post->caption ?? '';
        if (! empty($meta['hashtags'])) {
            $caption = trim($caption."\n\n".$meta['hashtags']);
        }

        $publicUrl = VideoResolver::publicUrl($post);
        if (! $publicUrl) {
            throw new RuntimeException('Instagram needs a public HTTPS video URL; upload the video or set a public URL on the post.');
        }

        return app(InstagramService::class)->publishReel($publicUrl, $caption, $account);
    }
}
