<?php

namespace Tests\Feature;

use App\Exceptions\NeedsReauthException;
use App\Jobs\PublishToFacebookReelsJob;
use App\Jobs\PublishToInstagramReelsJob;
use App\Jobs\PublishToTikTokJob;
use App\Jobs\PublishToYouTubeShortsJob;
use App\Models\Post;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connect\TokenManager;
use App\Services\PublishException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublishJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::disk('public')->put('videos/clip.mp4', str_repeat('x', 2048));
        Sleep::fake();
        config(['services.meta.graph_version' => 'v26.0']);

        // Token refresh is CONNECT's concern; publish jobs only need a token back.
        $this->mock(TokenManager::class, fn ($m) => $m->shouldReceive('freshAccessToken')->andReturn('TOKEN'));
    }

    private function makePost(User $user, array $attrs = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Clip',
            'caption' => 'hi',
            'hashtags' => ['fyp', 'clips'],
            'video_url' => 'https://cdn.test/storage/videos/clip.mp4',
            'video_path' => 'videos/clip.mp4',
            'source' => 'upload',
        ], $attrs));
    }

    private function account(User $user, string $provider, array $attrs = []): SocialAccount
    {
        return SocialAccount::create(array_merge([
            'user_id' => $user->id,
            'provider' => $provider,
            'platform_name' => $provider,
            'access_token' => 'stored',
            'status' => 'active',
        ], $attrs));
    }

    private function distribution(Post $post, string $platform, array $attrs = []): PostDistribution
    {
        return PostDistribution::create(array_merge([
            'post_id' => $post->id, 'platform' => $platform, 'status' => 'queued',
        ], $attrs));
    }

    // --- YouTube -------------------------------------------------------------

    public function test_youtube_job_does_a_resumable_upload_and_saves_thumbnail(): void
    {
        config(['services.youtube.privacy_status' => 'private']);
        Http::fake([
            'www.googleapis.com/upload/youtube/*' => Http::response('', 200, ['Location' => 'https://upload.youtube.test/session/1']),
            'upload.youtube.test/*' => Http::response([
                'id' => 'VID123',
                'status' => ['privacyStatus' => 'private'],
                'snippet' => ['thumbnails' => ['high' => ['url' => 'https://i.ytimg.com/vi/VID123/hq.jpg']]],
            ], 201),
        ]);

        $user = User::factory()->create();
        $post = $this->makePost($user);
        $this->account($user, 'youtube');
        $dist = $this->distribution($post, 'youtube');

        (new PublishToYouTubeShortsJob($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('published', $dist->status);
        $this->assertSame('VID123', $dist->external_id);
        $this->assertSame('private', $dist->privacy);
        $this->assertSame(1, $dist->attempts);
        $this->assertNotNull($dist->published_at);
        $this->assertSame('https://www.youtube.com/shorts/VID123', $dist->externalUrl());
        $this->assertSame('https://i.ytimg.com/vi/VID123/hq.jpg', $post->refresh()->thumbnail);

        Http::assertSent(function (Request $r) {
            return str_contains($r->url(), 'uploadType=resumable')
                && $r->hasHeader('X-Upload-Content-Length', '2048')
                && $r['status']['privacyStatus'] === 'private'
                && str_contains($r['snippet']['description'], '#fyp #clips');
        });
    }

    public function test_youtube_failure_records_the_platform_error_text(): void
    {
        Http::fake([
            'www.googleapis.com/upload/youtube/*' => Http::response(['error' => ['message' => 'The request cannot be completed because you have exceeded your quota.']], 403),
        ]);

        $user = User::factory()->create();
        $post = $this->makePost($user);
        $this->account($user, 'youtube');
        $dist = $this->distribution($post, 'youtube');

        (new PublishToYouTubeShortsJob($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('failed', $dist->status);
        $this->assertStringContainsString('exceeded your quota', $dist->error_message);
        $this->assertSame(1, $dist->attempts);
    }

    public function test_account_lookup_is_scoped_to_the_post_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->account($other, 'youtube'); // only the OTHER user has YouTube

        Http::fake();
        $post = $this->makePost($owner);
        $dist = $this->distribution($post, 'youtube');

        (new PublishToYouTubeShortsJob($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('failed', $dist->status);
        $this->assertStringContainsString('No youtube account', $dist->error_message);
        Http::assertNothingSent();
    }

    public function test_job_is_idempotent_when_external_id_is_set(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $post = $this->makePost($user);
        $this->account($user, 'youtube');
        $dist = $this->distribution($post, 'youtube', ['status' => 'published', 'external_id' => 'ALREADY']);

        (new PublishToYouTubeShortsJob($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('ALREADY', $dist->external_id);
        $this->assertSame(0, $dist->attempts);
        Http::assertNothingSent();
    }

    public static function jobs(): array
    {
        return [
            'youtube' => ['youtube', PublishToYouTubeShortsJob::class],
            'instagram' => ['instagram', PublishToInstagramReelsJob::class],
            'facebook' => ['facebook', PublishToFacebookReelsJob::class],
            'tiktok' => ['tiktok', PublishToTikTokJob::class],
        ];
    }

    /** A redelivered job (queue retry after a crash) must never post twice. */
    #[DataProvider('jobs')]
    public function test_every_job_skips_when_external_id_is_already_set(string $platform, string $jobClass): void
    {
        Http::fake();
        $user = User::factory()->create();
        $post = $this->makePost($user);
        $this->account($user, $platform, ['external_id' => 'ACC1']);
        $dist = $this->distribution($post, $platform, ['status' => 'uploading', 'external_id' => 'ALREADY', 'attempts' => 1]);

        (new $jobClass($dist->id))->handle();
        (new $jobClass($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('ALREADY', $dist->external_id);
        $this->assertSame(1, $dist->attempts);
        Http::assertNothingSent();
    }

    public function test_needs_reauth_fails_without_calling_the_platform(): void
    {
        $this->mock(TokenManager::class, fn ($m) => $m->shouldReceive('freshAccessToken')
            ->andThrow(new NeedsReauthException('The youtube account needs to be reconnected.')));
        Http::fake();

        $user = User::factory()->create();
        $post = $this->makePost($user);
        $this->account($user, 'youtube');
        $dist = $this->distribution($post, 'youtube');

        (new PublishToYouTubeShortsJob($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('failed', $dist->status);
        $this->assertSame('The youtube account needs to be reconnected.', $dist->error_message);
        Http::assertNothingSent();
    }

    // --- Instagram -----------------------------------------------------------

    public function test_instagram_job_creates_container_polls_and_publishes(): void
    {
        Http::fake([
            'graph.facebook.com/v26.0/17841400000/media_publish*' => Http::response(['id' => 'IG999']),
            'graph.facebook.com/v26.0/17841400000/media*' => Http::response(['id' => 'CONT1']),
            'graph.facebook.com/v26.0/CONT1*' => Http::sequence()
                ->push(['status_code' => 'IN_PROGRESS'])
                ->push(['status_code' => 'FINISHED']),
            'graph.facebook.com/v26.0/IG999*' => Http::response([
                'permalink' => 'https://www.instagram.com/reel/abc/',
                'thumbnail_url' => 'https://scontent.cdninstagram.com/thumb.jpg',
            ]),
        ]);

        $user = User::factory()->create();
        $post = $this->makePost($user);
        $this->account($user, 'instagram', ['external_id' => '17841400000']);
        $dist = $this->distribution($post, 'instagram');

        (new PublishToInstagramReelsJob($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('published', $dist->status);
        $this->assertSame('IG999', $dist->external_id);
        $this->assertSame('https://www.instagram.com/reel/abc/', $dist->externalUrl());
        $this->assertArrayNotHasKey('ig_container_id', (array) $dist->meta);
        $this->assertSame('https://scontent.cdninstagram.com/thumb.jpg', $post->refresh()->thumbnail);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/media')
            && $r['media_type'] === 'REELS'
            && $r['video_url'] === 'https://cdn.test/storage/videos/clip.mp4');
    }

    public function test_instagram_falls_back_to_meta_ig_user_id(): void
    {
        Http::fake([
            'graph.facebook.com/*/42/media_publish*' => Http::response(['id' => 'IG1']),
            'graph.facebook.com/*/42/media*' => Http::response(['id' => 'C1']),
            'graph.facebook.com/*/C1*' => Http::response(['status_code' => 'FINISHED']),
            'graph.facebook.com/*' => Http::response([]),
        ]);

        $user = User::factory()->create();
        $post = $this->makePost($user);
        $this->account($user, 'instagram', ['meta' => ['ig_user_id' => '42']]);
        $dist = $this->distribution($post, 'instagram');

        (new PublishToInstagramReelsJob($dist->id))->handle();

        $this->assertSame('IG1', $dist->refresh()->external_id);
    }

    public function test_instagram_container_error_fails_with_status_text(): void
    {
        Http::fake([
            'graph.facebook.com/*/1/media*' => Http::response(['id' => 'C2']),
            'graph.facebook.com/*/C2*' => Http::response(['status_code' => 'ERROR', 'status' => 'Error: Video aspect ratio not supported']),
        ]);

        $user = User::factory()->create();
        $post = $this->makePost($user);
        $this->account($user, 'instagram', ['external_id' => '1']);
        $dist = $this->distribution($post, 'instagram');

        (new PublishToInstagramReelsJob($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('failed', $dist->status);
        $this->assertStringContainsString('aspect ratio not supported', $dist->error_message);
        $this->assertArrayNotHasKey('ig_container_id', (array) $dist->meta);
    }

    public function test_instagram_resumes_polling_an_existing_container_without_recreating(): void
    {
        Http::fake([
            'graph.facebook.com/*/1/media_publish*' => Http::response(['id' => 'IG7']),
            'graph.facebook.com/*/C9*' => Http::response(['status_code' => 'FINISHED']),
            'graph.facebook.com/*' => Http::response([]),
        ]);

        $user = User::factory()->create();
        $post = $this->makePost($user);
        $this->account($user, 'instagram', ['external_id' => '1']);
        $dist = $this->distribution($post, 'instagram', [
            'status' => 'processing', 'attempts' => 1,
            'meta' => ['ig_container_id' => 'C9', 'awaiting_poll' => true],
        ]);

        (new PublishToInstagramReelsJob($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('IG7', $dist->external_id);
        $this->assertSame(1, $dist->attempts, 'A poll continuation is not a new attempt.');
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/media'));
    }

    // --- Facebook ------------------------------------------------------------

    public function test_facebook_reel_upload_phases_publish_and_store_video_id(): void
    {
        Http::fake([
            'graph.facebook.com/v26.0/PAGE1/video_reels' => Http::sequence()
                ->push(['video_id' => 'V1', 'upload_url' => 'https://rupload.facebook.com/video-upload/v26.0/V1'])
                ->push(['success' => true]),
            'rupload.facebook.com/video-upload/v26.0/V1' => Http::response(['success' => true]),
            'graph.facebook.com/v26.0/V1?fields=status*' => Http::sequence()
                ->push(['status' => ['video_status' => 'upload_complete', 'uploading_phase' => ['status' => 'complete']]])
                ->push(['status' => ['video_status' => 'ready', 'publishing_phase' => ['status' => 'complete', 'publish_status' => 'published']]]),
            'graph.facebook.com/v26.0/V1?fields=permalink*' => Http::response([
                'permalink_url' => '/reel/V1', 'picture' => 'https://scontent.xx.fbcdn.net/pic.jpg', 'post_id' => 'PAGE1_55',
            ]),
        ]);

        $user = User::factory()->create();
        $post = $this->makePost($user);
        $this->account($user, 'facebook', ['external_id' => 'PAGE1']);
        $dist = $this->distribution($post, 'facebook');

        (new PublishToFacebookReelsJob($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('published', $dist->status);
        $this->assertSame('V1', $dist->external_id);
        $this->assertSame('PAGE1_55', $dist->meta['fb_post_id']);
        $this->assertSame('https://www.facebook.com/reel/V1', $dist->externalUrl());
        $this->assertSame('https://scontent.xx.fbcdn.net/pic.jpg', $post->refresh()->thumbnail);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'rupload.facebook.com')
            && $r->hasHeader('file_url', 'https://cdn.test/storage/videos/clip.mp4')
            && $r->hasHeader('Authorization', 'OAuth TOKEN'));
        Http::assertSent(fn (Request $r) => ($r['upload_phase'] ?? null) === 'finish'
            && $r['video_state'] === 'PUBLISHED'
            && str_contains($r['description'], '#fyp'));
    }

    public function test_facebook_api_error_is_recorded(): void
    {
        Http::fake([
            'graph.facebook.com/*/video_reels' => Http::response(['error' => ['message' => '(#200) Requires pages_manage_posts permission', 'code' => 200]], 403),
        ]);

        $user = User::factory()->create();
        $post = $this->makePost($user);
        $this->account($user, 'facebook', ['external_id' => 'PAGE1']);
        $dist = $this->distribution($post, 'facebook');

        (new PublishToFacebookReelsJob($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('failed', $dist->status);
        $this->assertStringContainsString('Requires pages_manage_posts permission', $dist->error_message);
    }

    // --- TikTok --------------------------------------------------------------

    public function test_tiktok_direct_post_file_upload_with_configured_privacy(): void
    {
        config(['services.tiktok_posting.privacy_level' => 'SELF_ONLY', 'services.tiktok_posting.source' => 'FILE_UPLOAD']);
        Http::fake([
            'open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response([
                'data' => ['privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'SELF_ONLY'], 'max_video_post_duration_sec' => 600],
                'error' => ['code' => 'ok', 'message' => ''],
            ]),
            'open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
                'data' => ['publish_id' => 'v_pub_1', 'upload_url' => 'https://open-upload.tiktokapis.com/upload/?token=abc'],
                'error' => ['code' => 'ok'],
            ]),
            'open-upload.tiktokapis.com/*' => Http::response('', 201),
            'open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::sequence()
                ->push(['data' => ['status' => 'PROCESSING_UPLOAD'], 'error' => ['code' => 'ok']])
                ->push(['data' => ['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => [7300000000001]], 'error' => ['code' => 'ok']]),
            'open.tiktokapis.com/v2/video/query/*' => Http::response([
                'data' => ['videos' => [['id' => '7300000000001', 'cover_image_url' => 'https://p16.tiktokcdn.com/cover.jpg', 'share_url' => 'https://www.tiktok.com/@me/video/7300000000001']]],
                'error' => ['code' => 'ok'],
            ]),
        ]);

        $user = User::factory()->create();
        $post = $this->makePost($user);
        $this->account($user, 'tiktok', ['handle' => 'me']);
        $dist = $this->distribution($post, 'tiktok');

        (new PublishToTikTokJob($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('published', $dist->status);
        $this->assertSame('7300000000001', $dist->external_id);
        $this->assertSame('SELF_ONLY', $dist->privacy);
        $this->assertSame('https://p16.tiktokcdn.com/cover.jpg', $post->refresh()->thumbnail);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/video/init/')
            && $r['post_info']['privacy_level'] === 'SELF_ONLY'
            && $r['source_info']['source'] === 'FILE_UPLOAD'
            && $r['source_info']['video_size'] === 2048
            && $r['source_info']['total_chunk_count'] === 1);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'open-upload.tiktokapis.com')
            && $r->hasHeader('Content-Range', 'bytes 0-2047/2048'));
    }

    public function test_tiktok_rejects_a_privacy_level_the_creator_cannot_use(): void
    {
        // Audited app: the configured level is used (unaudited forces SELF_ONLY).
        config(['services.tiktok_posting.privacy_level' => 'PUBLIC_TO_EVERYONE', 'clippipeline.tiktok_audited' => true]);
        Http::fake([
            'open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response([
                'data' => ['privacy_level_options' => ['SELF_ONLY']], 'error' => ['code' => 'ok'],
            ]),
        ]);

        $user = User::factory()->create();
        $post = $this->makePost($user);
        $this->account($user, 'tiktok');
        $dist = $this->distribution($post, 'tiktok');

        (new PublishToTikTokJob($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('failed', $dist->status);
        $this->assertStringContainsString('PUBLIC_TO_EVERYONE', $dist->error_message);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/video/init/'));
    }

    public function test_tiktok_failed_status_records_fail_reason(): void
    {
        Http::fake([
            'open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response(['data' => ['privacy_level_options' => ['SELF_ONLY']], 'error' => ['code' => 'ok']]),
            'open.tiktokapis.com/v2/post/publish/video/init/' => Http::response(['data' => ['publish_id' => 'p2', 'upload_url' => 'https://open-upload.tiktokapis.com/u'], 'error' => ['code' => 'ok']]),
            'open-upload.tiktokapis.com/*' => Http::response('', 201),
            'open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response(['data' => ['status' => 'FAILED', 'fail_reason' => 'video_pull_failed'], 'error' => ['code' => 'ok']]),
        ]);

        $user = User::factory()->create();
        $post = $this->makePost($user);
        $this->account($user, 'tiktok');
        $dist = $this->distribution($post, 'tiktok');

        (new PublishToTikTokJob($dist->id))->handle();

        $dist->refresh();
        $this->assertSame('failed', $dist->status);
        $this->assertStringContainsString('video_pull_failed', $dist->error_message);
    }
    // --- Queue behaviour -----------------------------------------------------

    public function test_platform_processing_is_polled_by_releasing_the_job(): void
    {
        Http::fake([
            'graph.facebook.com/*/1/media*' => Http::response(['id' => 'C3']),
            'graph.facebook.com/*/C3*' => Http::response(['status_code' => 'IN_PROGRESS']),
        ]);

        $user = User::factory()->create();
        $post = $this->makePost($user);
        $this->account($user, 'instagram', ['external_id' => '1']);
        $dist = $this->distribution($post, 'instagram');

        $job = (new PublishToInstagramReelsJob($dist->id))->withFakeQueueInteractions();
        $job->handle();

        $job->assertReleased(delay: 60);
        $dist->refresh();
        $this->assertSame('processing', $dist->status);
        $this->assertSame('C3', $dist->meta['ig_container_id']);
        $this->assertTrue($dist->meta['awaiting_poll']);
    }

    public function test_retryable_errors_rethrow_for_backoff_then_fail_when_exhausted(): void
    {
        Http::fake([
            'www.googleapis.com/upload/youtube/*' => Http::response(['error' => ['message' => 'Backend Error']], 503),
        ]);

        $user = User::factory()->create();
        $post = $this->makePost($user);
        $this->account($user, 'youtube');
        $dist = $this->distribution($post, 'youtube');

        $job = (new PublishToYouTubeShortsJob($dist->id))->withFakeQueueInteractions();

        try {
            $job->handle();
            $this->fail('A retryable error must be rethrown so the queue retries.');
        } catch (PublishException $e) {
            $this->assertTrue($e->retryable);
        }

        $dist->refresh();
        $this->assertSame('uploading', $dist->status);
        $this->assertStringContainsString('Backend Error', $dist->error_message);

        $job->failed($e);
        $this->assertSame('failed', $dist->refresh()->status);
        $this->assertSame(3, $job->maxExceptions);
        $this->assertLessThan(600, $job->timeout);
    }
}
