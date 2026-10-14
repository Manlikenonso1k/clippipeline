<?php

namespace App\Jobs;

use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Services\YouTubeService;
use App\Services\YouTubeSessionExpired;

/** YouTube Shorts via videos.insert resumable upload (resumes across retries). */
class PublishToYouTubeShortsJob extends PublishJob
{
    protected function platform(): string
    {
        return 'youtube';
    }

    protected function publish(PostDistribution $distribution, SocialAccount $account, string $token): ?PublishResult
    {
        $post = $distribution->post;
        $youtube = app(YouTubeService::class);
        $path = $this->localVideoPath($post);
        // The user's choice (P-04); forced private while the YouTube audit is pending.
        $privacy = (string) (((array) ($distribution->options ?? []))['privacy_status'] ?? config('services.youtube.privacy_status', 'private'));
        if (! config('clippipeline.youtube_audited') || ! in_array($privacy, ['public', 'private', 'unlisted'], true)) {
            $privacy = 'private';
        }

        $title = $this->title($post, $distribution);
        if (! str_contains(mb_strtolower($title), '#shorts')) {
            $title = trim(mb_substr($title, 0, 91).' #Shorts');
        }

        $sessionUri = ((array) $distribution->meta)['yt_upload_uri'] ?? null;

        try {
            if (! $sessionUri) {
                $sessionUri = $youtube->startSession($token, $path, $title, $this->caption($post, $distribution), $post->hashtagList(), $privacy);
                $distribution->privacy = $privacy;
                $this->remember($distribution, ['yt_upload_uri' => $sessionUri]);
                $video = $youtube->uploadToSession($sessionUri, $token, $path);
            } else {
                $video = $youtube->uploadToSession($sessionUri, $token, $path, resume: true);
            }
        } catch (YouTubeSessionExpired $e) {
            $this->forget($distribution, ['yt_upload_uri']);
            throw $e;
        }

        $id = (string) $video['id'];
        $distribution->privacy = $video['status']['privacyStatus'] ?? $privacy;

        return new PublishResult(
            externalId: $id,
            url: 'https://www.youtube.com/shorts/'.$id,
            thumbnail: $youtube->thumbnailFrom($video),
        );
    }
}
