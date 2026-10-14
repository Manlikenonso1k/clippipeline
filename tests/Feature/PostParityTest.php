<?php

namespace Tests\Feature;

use App\Jobs\PublishToTikTokJob;
use App\Models\Post;
use App\Models\PostAnalytic;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Media\Mp4Duration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** CONTRACT §12 + COMPLIANCE P-02/P-03/P-04/P-05/T-09. */
class PostParityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $creatorOptions = ['PUBLIC_TO_EVERYONE', 'SELF_ONLY'];

    private int $igUsage = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://clip.test', 'services.meta.graph_version' => 'v26.0']);
        Storage::fake('public');
        Queue::fake();
        Http::fake([
            'clip.test/*' => Http::response('', 200),
            'open.tiktokapis.com/v2/post/publish/creator_info/query/' => fn () => Http::response([
                'data' => ['creator_nickname' => 'Nonso', 'privacy_level_options' => $this->creatorOptions, 'max_video_post_duration_sec' => 60, 'stitch_disabled' => true],
                'error' => ['code' => 'ok'],
            ]),
            'graph.facebook.com/v26.0/17841/content_publishing_limit*' => fn () => Http::response(['data' => [['quota_usage' => $this->igUsage, 'config' => ['quota_total' => 100, 'quota_duration' => 86400]]]]),
        ]);
        Http::preventStrayRequests();

        $this->user = User::factory()->create();
        foreach (['youtube' => null, 'facebook' => 'page1', 'instagram' => '17841', 'tiktok' => 'open1'] as $p => $ext) {
            SocialAccount::create(['user_id' => $this->user->id, 'provider' => $p, 'platform_name' => $p, 'access_token' => 't', 'status' => 'active', 'external_id' => $ext,
                'meta' => ['validated_at' => now()->toIso8601String()], 'expires_at' => now()->addDay()]);
        }
        Sanctum::actingAs($this->user);
    }

    private function mp4(float $seconds): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'mp4');
        file_put_contents($path, Mp4Duration::fixture($seconds));

        return new UploadedFile($path, 'clip.mp4', 'video/mp4', null, true);
    }

    private function create(array $data)
    {
        return $this->post('/api/posts', $data + ['video' => $this->mp4(30), 'title' => 'Clip'], ['Accept' => 'application/json']);
    }

    private function tiktok(array $overrides = []): array
    {
        return ['tiktok' => $overrides + ['privacy_level' => 'SELF_ONLY', 'music_consent' => '1']];
    }

    public function test_mp4_duration_reader(): void
    {
        $file = $this->mp4(42.5);
        $this->assertSame(42.5, Mp4Duration::seconds($file->getRealPath()));
        $this->assertNull(Mp4Duration::seconds(UploadedFile::fake()->create('x.mp4', 1)->getRealPath()));
    }

    public function test_youtube_requires_privacy_and_guidelines_and_is_forced_private(): void
    {
        $this->create(['platforms' => ['youtube']])->assertStatus(422)
            ->assertJsonValidationErrors(['platform_options.youtube.privacy_status', 'platform_options.youtube.guidelines_confirmed']);

        $this->create(['platforms' => ['youtube'], 'platform_options' => ['youtube' => ['privacy_status' => 'public', 'guidelines_confirmed' => '1']]])
            ->assertCreated()
            ->assertJsonPath('distributions.0.options.privacy_status', 'private')
            ->assertJsonPath('distributions.0.options.requested_privacy_status', 'public');

        config(['clippipeline.youtube_audited' => true]);
        $this->create(['platforms' => ['youtube'], 'platform_options' => ['youtube' => ['privacy_status' => 'unlisted', 'guidelines_confirmed' => '1']]])
            ->assertCreated()->assertJsonPath('distributions.0.options.privacy_status', 'unlisted');
    }

    public function test_tiktok_rules_t09(): void
    {
        // Missing consent + privacy.
        $this->create(['platforms' => ['tiktok']])->assertStatus(422)
            ->assertJsonValidationErrors(['platform_options.tiktok.privacy_level', 'platform_options.tiktok.music_consent']);

        // Unaudited: only SELF_ONLY offered, so PUBLIC is rejected.
        $this->create(['platforms' => ['tiktok'], 'platform_options' => $this->tiktok(['privacy_level' => 'PUBLIC_TO_EVERYONE'])])
            ->assertStatus(422)->assertJsonValidationErrors(['platform_options.tiktok.privacy_level']);

        // Branded content cannot be private.
        $this->create(['platforms' => ['tiktok'], 'platform_options' => $this->tiktok(['brand_content_toggle' => '1'])])
            ->assertStatus(422)->assertJsonValidationErrors(['platform_options.tiktok.privacy_level']);

        // Longer than the creator max (60s).
        $this->create(['platforms' => ['tiktok'], 'video' => $this->mp4(61), 'platform_options' => $this->tiktok()])
            ->assertStatus(422)->assertJsonValidationErrors(['video']);

        $res = $this->create(['platforms' => ['tiktok'], 'platform_options' => $this->tiktok(['disable_comment' => '0', 'disable_stitch' => '0', 'is_aigc' => '1'])])
            ->assertCreated()
            ->assertJsonPath('video_duration_seconds', null);

        $options = PostDistribution::where('platform', 'tiktok')->firstOrFail()->options;
        $this->assertSame('SELF_ONLY', $options['privacy_level']);
        $this->assertFalse($options['disable_comment']);
        $this->assertTrue($options['disable_duet']);  // default off
        $this->assertTrue($options['disable_stitch']); // creator disabled it
        $this->assertTrue($options['is_aigc']);
        $this->assertSame(30.0, Post::latest('id')->first()->video_duration_seconds);
        Queue::assertPushed(PublishToTikTokJob::class);
    }

    public function test_facebook_and_instagram_caps(): void
    {
        $this->create(['platforms' => ['facebook'], 'video' => $this->mp4(120)])
            ->assertStatus(422)->assertJsonValidationErrors(['video']);
        $this->create(['platforms' => ['facebook'], 'video' => $this->mp4(2), 'video_duration_seconds' => 10])
            ->assertStatus(422); // MP4 header wins over the client value

        $this->create(['platforms' => ['facebook', 'instagram']])->assertCreated();

        // 30 Facebook Reels / 24h.
        $p = Post::firstOrFail();
        for ($i = 0; $i < 29; $i++) {
            PostDistribution::create(['post_id' => $p->id, 'platform' => 'facebook', 'status' => 'published']);
        }
        $this->create(['platforms' => ['facebook']])->assertStatus(422)
            ->assertJsonFragment(['Facebook limit reached: 30 Reels per Page in 24 hours. Try again later.']);

        // Instagram content_publishing_limit.
        $this->igUsage = 100;
        $this->create(['platforms' => ['instagram']])->assertStatus(422)
            ->assertJsonFragment(['Instagram limit reached: 100 posts in 24 hours. Try again later.']);
    }

    public function test_search_platform_filter_and_scoping(): void
    {
        $a = Post::factory()->upload()->create(['user_id' => $this->user->id, 'title' => 'Beach Day', 'caption' => 'sun']);
        $b = Post::factory()->upload()->create(['user_id' => $this->user->id, 'title' => 'Night', 'caption' => 'city LIGHTS']);
        $other = Post::factory()->upload()->create(['title' => 'Beach other user']);
        PostDistribution::create(['post_id' => $a->id, 'platform' => 'tiktok', 'status' => 'published']);
        PostDistribution::create(['post_id' => $b->id, 'platform' => 'youtube', 'status' => 'published']);
        PostDistribution::create(['post_id' => $other->id, 'platform' => 'tiktok', 'status' => 'published']);

        $this->assertSame([$a->id], array_column($this->getJson('/api/posts?q=beach')->assertOk()->json(), 'id'));
        $this->assertSame([$b->id], array_column($this->getJson('/api/posts?q=lights')->json(), 'id'));
        $this->assertSame([$a->id], array_column($this->getJson('/api/posts?platform=tiktok')->json(), 'id'));
        $this->assertSame([], $this->getJson('/api/posts?q=beach&platform=youtube')->json());
        $this->getJson('/api/posts?platform=myspace')->assertStatus(422);
    }

    public function test_delete_post_removes_local_data_only(): void
    {
        Storage::disk('public')->put('videos/x.mp4', 'v');
        $post = Post::factory()->upload()->create(['user_id' => $this->user->id, 'video_path' => 'videos/x.mp4', 'download_path' => null, 'scheduled_at' => now()->addDay()]);
        $dist = PostDistribution::create(['post_id' => $post->id, 'platform' => 'youtube', 'status' => 'queued']);
        PostAnalytic::factory()->forDistribution($dist)->create();
        $other = Post::factory()->upload()->create();

        $this->deleteJson("/api/posts/{$other->id}")->assertNotFound();
        $this->deleteJson("/api/posts/{$post->id}")->assertNoContent();

        $this->assertNull(Post::find($post->id));
        $this->assertSame(0, PostDistribution::where('post_id', $post->id)->count());
        $this->assertSame(0, PostAnalytic::where('distribution_id', $dist->id)->count());
        Storage::disk('public')->assertMissing('videos/x.mp4');
        $this->assertNotNull($other->fresh());
        Http::assertNotSent(fn ($r) => $r->method() === 'DELETE'); // nothing deleted on platforms

        // The queued job for the deleted distribution is a no-op.
        (new PublishToTikTokJob($dist->id))->handle();
        $this->assertSame(0, PostDistribution::count());
    }
}
