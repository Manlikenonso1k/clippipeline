<?php

namespace Tests\Feature;

use App\Jobs\PublishToInstagramReelsJob;
use App\Jobs\PublishToYouTubeShortsJob;
use App\Models\Post;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

class PublishJobTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $user, array $attrs = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Clip',
            'caption' => 'hi',
            'video_url' => 'https://cdn.test/v.mp4',
        ], $attrs));
    }

    public function test_youtube_job_publishes_and_records_distribution(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('videos/clip.mp4', 'bytes');

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29', 'expires_in' => 3600]),
            'googleapis.com/upload/youtube/*' => Http::response(['id' => 'VID123'], 200, ['Location' => 'https://upload.youtube.test/session']),
            'upload.youtube.test/*' => Http::response(['id' => 'VID123']),
        ]);

        $user = User::factory()->create();
        $post = $this->makePost($user, ['download_path' => 'videos/clip.mp4']);
        SocialAccount::create([
            'user_id' => $user->id, 'provider' => 'youtube', 'platform_name' => 'youtube',
            'access_token' => 'old', 'refresh_token' => 'refresh', 'expires_at' => now()->subDay(),
        ]);
        $dist = PostDistribution::create(['post_id' => $post->id, 'platform' => 'youtube', 'status' => 'queued']);

        (new PublishToYouTubeShortsJob($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('published', $dist->status);
        $this->assertSame('VID123', $dist->external_id);
        $this->assertNotNull($dist->published_at);
    }

    public function test_instagram_job_publishes_and_records_distribution(): void
    {
        Http::fake([
            'graph.facebook.com/*/media_publish' => Http::response(['id' => 'IG999']),
            'graph.facebook.com/*/media' => Http::response(['id' => 'CREATION1']),
            'graph.facebook.com/*' => Http::response(['status_code' => 'FINISHED']),
            '*' => Http::response('', 200),
        ]);

        $user = User::factory()->create();
        $post = $this->makePost($user);
        SocialAccount::create([
            'user_id' => $user->id, 'provider' => 'instagram', 'platform_name' => 'instagram',
            'access_token' => 'token', 'meta' => ['ig_user_id' => '17841400000'],
        ]);
        $dist = PostDistribution::create(['post_id' => $post->id, 'platform' => 'instagram', 'status' => 'queued']);

        (new PublishToInstagramReelsJob($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('published', $dist->status);
        $this->assertSame('IG999', $dist->external_id);
    }

    public function test_failed_upload_records_the_error_message(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('videos/clip.mp4', 'bytes');
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29', 'expires_in' => 3600]),
            'googleapis.com/upload/youtube/*' => Http::response(['error' => 'quota'], 403),
        ]);

        $user = User::factory()->create();
        $post = $this->makePost($user, ['download_path' => 'videos/clip.mp4']);
        SocialAccount::create([
            'user_id' => $user->id, 'provider' => 'youtube', 'platform_name' => 'youtube',
            'access_token' => 'tok', 'refresh_token' => 'r', 'expires_at' => now()->addDay(),
        ]);
        $dist = PostDistribution::create(['post_id' => $post->id, 'platform' => 'youtube', 'status' => 'queued']);

        try {
            (new PublishToYouTubeShortsJob($dist->id))->handle();
        } catch (Throwable $e) {
            // job rethrows so the queue can retry; we assert the recorded state
        }

        $dist->refresh();
        $this->assertSame('failed', $dist->status);
        $this->assertNotEmpty($dist->error);
    }

    public function test_account_lookup_is_scoped_to_the_post_owner(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('videos/clip.mp4', 'bytes');

        $owner = User::factory()->create();
        $other = User::factory()->create();

        SocialAccount::create(['user_id' => $other->id, 'provider' => 'youtube', 'platform_name' => 'youtube', 'access_token' => 'OTHER']);
        $ownerAccount = SocialAccount::create(['user_id' => $owner->id, 'provider' => 'youtube', 'platform_name' => 'youtube', 'access_token' => 'OWNER']);

        $captured = null;
        $this->app->instance(YouTubeService::class, new class($captured) extends YouTubeService
        {
            public function __construct(public &$captured) {}

            public function uploadShort(string $filePath, string $title, string $description, SocialAccount $account, string $privacyStatus = 'public'): string
            {
                $this->captured = $account->id;

                return 'VIDSCOPED';
            }
        });

        $post = $this->makePost($owner, ['download_path' => 'videos/clip.mp4']);
        $dist = PostDistribution::create(['post_id' => $post->id, 'platform' => 'youtube', 'status' => 'queued']);

        (new PublishToYouTubeShortsJob($dist->id))->handle();

        $this->assertSame($ownerAccount->id, $captured, 'Job must use the post owner\'s account, not another user\'s.');
    }

    public function test_job_is_idempotent_when_already_published(): void
    {
        $this->app->instance(YouTubeService::class, new class extends YouTubeService
        {
            public function uploadShort(string $filePath, string $title, string $description, SocialAccount $account, string $privacyStatus = 'public'): string
            {
                throw new \RuntimeException('should not upload again');
            }
        });

        $user = User::factory()->create();
        $post = $this->makePost($user);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'youtube', 'platform_name' => 'youtube', 'access_token' => 'tok']);
        $dist = PostDistribution::create([
            'post_id' => $post->id, 'platform' => 'youtube', 'status' => 'published', 'external_id' => 'ALREADY',
        ]);

        (new PublishToYouTubeShortsJob($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('published', $dist->status);
        $this->assertSame('ALREADY', $dist->external_id);
    }
}
