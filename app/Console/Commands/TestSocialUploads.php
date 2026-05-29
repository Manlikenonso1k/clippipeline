<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\YouTubeService;
use App\Services\InstagramService;

class TestSocialUploads extends Command
{
    protected $signature = 'social:test-upload {--file=} {--postId=}';
    protected $description = 'Test uploading a local file or a post to YouTube Shorts and Instagram Reels using stored credentials';

    public function handle(YouTubeService $yt, InstagramService $ig)
    {
        $fileOpt = $this->option('file');
        $postIdOpt = $this->option('postId');

        if (! $fileOpt && ! $postIdOpt) {
            $this->error('Provide either --file=path or --postId=ID');
            return 1;
        }

        // Prepare file
        if ($postIdOpt) {
            $post = DB::table('posts')->where('id', $postIdOpt)->first();
            if (! $post) {
                $this->error('Post not found');
                return 1;
            }

            $relative = $post->download_path;
            $filePath = storage_path('app/'.ltrim($relative, '\\/'));
            $caption = $post->caption ?? '';
        } else {
            $src = $fileOpt;
            if (! file_exists($src)) {
                $this->error('File does not exist: '.$src);
                return 1;
            }

            // Copy into storage/app/test_uploads for InstagramService compatibility
            $destDir = storage_path('app/test_uploads');
            if (! is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            $basename = basename($src);
            $dest = $destDir.DIRECTORY_SEPARATOR.$basename;
            copy($src, $dest);

            $relative = 'test_uploads/'.$basename;
            $filePath = $dest;
            $caption = 'Test upload';
        }

        $this->info('Using file: '.$filePath);

        // YouTube
        $ytAccount = DB::table('social_accounts')->where('provider', 'youtube')->first();
        if ($ytAccount) {
            $this->info('Uploading to YouTube...');
            $title = trim($caption.' #Shorts');
            $description = $caption;
            $external = $yt->uploadShort($filePath, $title, $description, $ytAccount->refresh_token ?? null);
            if ($external) {
                $this->info('YouTube upload simulated/published: '.$external);
            } else {
                $this->error('YouTube upload failed (check logs and credentials)');
            }
        } else {
            $this->warn('No YouTube account connected. Skipping YouTube.');
        }

        // Instagram
        $igAccount = DB::table('social_accounts')->where('provider', 'instagram')->first();
        if ($igAccount) {
            $this->info('Publishing to Instagram...');
            $meta = json_decode($igAccount->meta ?? '{}' , true) ?: [];
            $access = $igAccount->access_token ?? null;
            $externalIg = $ig->publishReel($relative, $caption, $meta, $access);
            if ($externalIg) {
                $this->info('Instagram publish simulated/published: '.$externalIg);
            } else {
                $this->error('Instagram publish failed (check logs and credentials)');
            }
        } else {
            $this->warn('No Instagram account connected. Skipping Instagram.');
        }

        $this->info('Test run complete. Check post_distributions table for any records created/updated.');
        return 0;
    }
}
