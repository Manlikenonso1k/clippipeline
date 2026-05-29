<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\InstagramService;

class PublishToInstagramReelsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $postId;

    public function __construct(int $postId)
    {
        $this->postId = $postId;
    }

    public function handle(InstagramService $ig)
    {
        $post = DB::table('posts')->where('id', $this->postId)->first();
        if (! $post) {
            return;
        }

        $account = DB::table('social_accounts')->where('provider', 'instagram')->first();
        if (! $account) {
            DB::table('post_distributions')->updateOrInsert(
                ['post_id' => $post->id, 'platform' => 'instagram'],
                ['status' => 'failed', 'error_message' => 'No Instagram account connected', 'updated_at' => now(), 'created_at' => now()]
            );
            return;
        }

        $filePath = $post->download_path;
        if (! $filePath || ! file_exists(storage_path('app/'.ltrim($filePath, '\\/')))) {
            DB::table('post_distributions')->updateOrInsert(
                ['post_id' => $post->id, 'platform' => 'instagram'],
                ['status' => 'failed', 'error_message' => 'Downloaded file not found', 'updated_at' => now(), 'created_at' => now()]
            );
            return;
        }

        $meta = json_decode($account->meta ?? '{}', true) ?: [];
        $accessToken = $account->access_token ?? null;

        $externalId = $ig->publishReel($filePath, $post->caption ?? '', $meta, $accessToken);

        if (! $externalId) {
            DB::table('post_distributions')->updateOrInsert(
                ['post_id' => $post->id, 'platform' => 'instagram'],
                ['status' => 'failed', 'error_message' => 'Instagram publish failed', 'updated_at' => now(), 'created_at' => now()]
            );
            return;
        }

        DB::table('post_distributions')->updateOrInsert(
            ['post_id' => $post->id, 'platform' => 'instagram'],
            [
                'status' => 'published',
                'external_id' => $externalId,
                'published_at' => now(),
                'meta' => json_encode(['note' => 'uploaded via InstagramService']),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
