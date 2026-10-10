<?php

namespace App\Jobs;

use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Services\YouTubeService;
use App\Support\VideoResolver;

class PublishToYouTubeShortsJob extends PublishJob
{
    protected function provider(): string
    {
        return 'youtube';
    }

    protected function publish(PostDistribution $distribution, SocialAccount $account): string
    {
        $post = $distribution->post;
        $meta = (array) $distribution->meta;

        $title = trim(($meta['title'] ?? $post->title ?? 'Untitled').' #Shorts');
        $description = $meta['description'] ?? $post->caption ?? '';
        if (! empty($meta['hashtags'])) {
            $description = trim($description."\n\n".$meta['hashtags']);
        }
        $privacy = $meta['privacy'] ?? 'public';

        $localPath = VideoResolver::localPath($post);

        return app(YouTubeService::class)->uploadShort($localPath, $title, $description, $account, $privacy);
    }
}
