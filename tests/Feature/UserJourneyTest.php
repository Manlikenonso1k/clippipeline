<?php

namespace Tests\Feature;

use App\Jobs\PublishToFacebookReelsJob;
use App\Jobs\PublishToInstagramReelsJob;
use App\Jobs\PublishToTikTokJob;
use App\Jobs\PublishToYouTubeShortsJob;
use App\Jobs\PurgeUserDataJob;
use App\Models\DeviceToken;
use App\Models\Post;
use App\Models\PostAnalytic;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connect\OAuthState;
use App\Services\Media\Mp4Duration;
use Carbon\CarbonImmutable;
use Illuminate\Bus\UniqueLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * USER-SIM journeys (docs/USER_SIM.md): a creator who signed in and connected
 * TikTok, YouTube, a Facebook Page and Instagram, driving the API exactly the way
 * the mobile app does (paths/fields from clippipeline_mobile/src/api/hooks.ts).
 *
 * Every platform call goes through one fake router that answers like the real
 * API would; anything it does not recognise is recorded and fails the test.
 */
class UserJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const G = 'v26.0';

    private const PAGE_ID = '100000000000001';

    private const IG_USER = '17841400000000000';

    private const FB_USER = '900000000000001';

    private const FB_VIDEO = '1200000000000001';

    private const IG_CONTAINER = '17950000000000001';

    private const IG_MEDIA = '17900000000000001';

    private const YT_VIDEO = 'dQw4w9WgXcQ';

    private const TT_PUBLISH = 'v_pub_file~v2-1.7300000000000000001';

    private const TT_VIDEO = '7300000000000000001';

    private User $user;

    /** @var array<string, SocialAccount> */
    private array $accounts = [];

    /** Requests the router did not recognise (a stray platform call). */
    private array $unexpected = [];

    // --- mutable platform behaviour -------------------------------------------

    private array $creatorOptions = ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'SELF_ONLY'];

    private string $igFinalStatus = 'FINISHED';

    private int $igPolls = 0;

    private int $fbPolls = 0;

    private int $ttPolls = 0;

    /** TikTok only returns a public post id for public, moderated posts. */
    private bool $ttPublic = false;

    /** Pushed job objects already run by runQueuedPublishJobs(). */
    private array $ran = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
        config([
            'app.url' => 'https://clip.test',
            'services.meta.graph_version' => self::G,
            'services.google.client_id' => 'gid', 'services.google.client_secret' => 'gsecret',
            'services.google.redirect' => 'https://clip.test/auth/callback/google',
            'services.facebook.client_id' => 'fid', 'services.facebook.client_secret' => 'fsecret',
            'services.facebook.redirect' => 'https://clip.test/integrations/callback/instagram',
            'services.tiktok.client_id' => 'tkey', 'services.tiktok.client_secret' => 'tsecret',
            'services.tiktok.redirect' => 'https://clip.test/integrations/callback/tiktok',
            'services.youtube.redirect' => 'https://clip.test/integrations/callback/youtube',
            'clippipeline.tiktok_audited' => false,
            'clippipeline.youtube_audited' => false,
        ]);
        Storage::fake('public');
        Sleep::fake();
        Http::fake(fn (Request $r) => $this->route($r));
        Http::preventStrayRequests();

        $this->user = User::factory()->create(['name' => 'Nonso Creator', 'email' => 'nonso@example.com']);
        DeviceToken::create(['user_id' => $this->user->id, 'token' => 'ExponentPushToken[abc123]', 'platform' => 'android']);

        $rows = [
            'youtube' => ['external_id' => 'UCxxxxxxxxxxxxxxxxxxxx01', 'handle' => '@nonsoclips', 'display_name' => 'Nonso Clips', 'follower_count' => 1200],
            'facebook' => ['external_id' => self::PAGE_ID, 'handle' => 'Nonso Clips Page', 'display_name' => 'Nonso Clips Page', 'follower_count' => 800,
                'meta' => ['fb_user_id' => self::FB_USER, 'validated_at' => now()->toIso8601String()]],
            'instagram' => ['external_id' => self::IG_USER, 'handle' => '@nonso.clips', 'display_name' => 'Nonso Clips', 'follower_count' => 3400,
                'meta' => ['ig_user_id' => self::IG_USER, 'fb_user_id' => self::FB_USER, 'validated_at' => now()->toIso8601String()]],
            'tiktok' => ['external_id' => 'open-id-nonso', 'handle' => '@nonso', 'display_name' => 'Nonso', 'follower_count' => 5000],
        ];
        foreach ($rows as $platform => $attrs) {
            $this->accounts[$platform] = SocialAccount::factory()->for($this->user)->platform($platform)->create($attrs + [
                'access_token' => 'tok-'.$platform,
                'refresh_token' => $platform === 'youtube' || $platform === 'tiktok' ? 'rt-'.$platform : null,
                'expires_at' => now()->addDays(30),
                'avatar_url' => "https://cdn.test/{$platform}.png",
            ]);
        }
    }

    protected function tearDown(): void
    {
        $unexpected = $this->unexpected;
        parent::tearDown();
        $this->assertSame([], $unexpected, 'Unexpected platform calls: '.json_encode($unexpected));
    }

    // =========================================================================
    // a. First open
    // =========================================================================

    public function test_a_first_open_shows_plan_accounts_and_empty_zero_analytics(): void
    {
        Sanctum::actingAs($this->user);

        $me = $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.name', 'Nonso Creator')
            ->assertJsonPath('user.email', 'nonso@example.com')
            ->assertJsonPath('plan.name', 'free')
            ->assertJsonPath('plan.clips_used', 0)
            ->assertJsonPath('plan.clips_limit', 10)
            ->assertJsonPath('sync.status', 'idle')       // nothing synced yet
            ->assertJsonPath('sync.last_synced_at', null)
            ->assertJsonPath('flags.tiktok_audited', false)
            ->assertJsonPath('flags.youtube_audited', false)
            ->assertJsonPath('preferences.auto_sync', true)
            ->assertJsonPath('preferences.notifications_enabled', true);
        $this->assertNotEmpty($me->json('plan.billing_url'));
        $this->assertStringEndsWith('/privacy', $me->json('flags.privacy_url'));
        $this->assertStringEndsWith('/terms', $me->json('flags.terms_url'));
        $this->assertStringEndsWith('/delete-account', $me->json('flags.delete_account_url'));

        $accounts = $this->getJson('/api/social-accounts')->assertOk()->assertJsonCount(4)->json();
        $this->assertSame(['youtube', 'facebook', 'instagram', 'tiktok'], array_column($accounts, 'platform'));
        $this->assertSame(['youtube', 'meta', 'meta', 'tiktok'], array_column($accounts, 'connect_provider'));
        $this->assertSame([true, true, true, true], array_column($accounts, 'connected'));
        $this->assertSame(['active', 'active', 'active', 'active'], array_column($accounts, 'status'));
        $this->assertSame([1200, 800, 3400, 5000], array_column($accounts, 'follower_count'));
        foreach ($accounts as $a) {
            $this->assertArrayNotHasKey('access_token', $a);
            $this->assertArrayNotHasKey('refresh_token', $a);
        }

        $overview = $this->getJson('/api/analytics/overview?period=7d')->assertOk()
            ->assertJsonPath('period', '7d')
            ->assertJsonPath('start', '2026-10-07')
            ->assertJsonPath('end', '2026-10-13')
            ->assertJsonPath('posts_published', 0)
            ->assertJsonPath('connected_accounts', 4)
            ->assertJsonPath('total_views', 0)
            ->json();
        foreach (['views', 'likes', 'comments', 'shares', 'saves', 'watch_time_seconds'] as $k) {
            $this->assertSame(0, $overview['totals'][$k], "totals.$k");
            $this->assertNull($overview['change'][$k], "change.$k");
        }
        $this->assertCount(7, $overview['trend']);
        $this->assertSame(0, array_sum(array_column($overview['trend'], 'views')));
        // Contract: followers come from follower snapshots, so before the first sync Home
        // shows 0 followers while Connect shows 10,400 (USER_SIM UX note).
        $this->assertSame(0, $overview['followers']['total']);

        $platforms = $this->getJson('/api/analytics/platforms?period=7d')->assertOk()->json('platforms');
        $this->assertSame(['youtube', 'facebook', 'instagram', 'tiktok'], array_column($platforms, 'platform'));
        $this->assertSame([true, true, true, true], array_column($platforms, 'connected'));

        $this->assertCount(7, $this->getJson('/api/analytics/timeseries?period=7d&platform=all')->assertOk()->json('points'));
        $this->getJson('/api/analytics/top-posts?period=7d&limit=5')->assertOk()->assertExactJson([]);
        $this->getJson('/api/posts?filter=all')->assertOk()->assertExactJson([]);
        $this->getJson('/api/notifications?limit=30')->assertOk()->assertJsonPath('unread_count', 0)->assertJsonPath('items', []);
    }

    // =========================================================================
    // b. Create a post to all four platforms and watch it publish
    // =========================================================================

    public function test_b_post_to_all_four_platforms_publishes_with_urls_and_thumbnail(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->user);

        // The TikTok form opens: creator_info first (T-01).
        $creator = $this->getJson('/api/tiktok/creator-info')->assertOk()
            ->assertJsonPath('creator_nickname', 'Nonso')
            ->assertJsonPath('can_post', true)
            ->assertJsonPath('audited', false)
            ->json();
        $this->assertSame(['SELF_ONLY'], $creator['privacy_level_options'], 'Unaudited app only offers SELF_ONLY.');

        $res = $this->createPost(['platforms' => ['youtube', 'facebook', 'instagram', 'tiktok']], $creator['privacy_level_options'][0]);
        $res->assertCreated()
            ->assertJsonPath('status', 'processing')
            ->assertJsonPath('source', 'upload')
            ->assertJsonPath('hashtags', ['fyp', 'clips'])
            ->assertJsonPath('thumbnail', null)
            ->assertJsonCount(4, 'distributions');
        $postId = $res->json('id');
        $this->assertStringStartsWith('https://clip.test/storage/videos/', $res->json('video_url'));
        $this->assertSame(['queued', 'queued', 'queued', 'queued'], array_column($res->json('distributions'), 'status'));

        $byPlatform = collect($res->json('distributions'))->keyBy('platform');
        $this->assertSame('private', $byPlatform['youtube']['options']['privacy_status'], 'YouTube forced private until audited.');
        $this->assertSame('SELF_ONLY', $byPlatform['tiktok']['options']['privacy_level']);
        $this->assertTrue($byPlatform['tiktok']['options']['disable_comment']);

        // Uploads never run inside the request: one job per platform.
        foreach ([PublishToYouTubeShortsJob::class, PublishToFacebookReelsJob::class, PublishToInstagramReelsJob::class, PublishToTikTokJob::class] as $job) {
            Queue::assertPushed($job, 1);
        }

        // Poll like the app (every 10 s while in flight) while the worker runs each job.
        $seen = [];
        foreach (['youtube', 'facebook', 'instagram', 'tiktok'] as $platform) {
            $this->assertSame([$platform], $this->runQueuedPublishJobs($platform));
            $poll = $this->getJson("/api/posts/{$postId}")->assertOk();
            $seen[] = $poll->json('status');
            $this->assertSame('published', collect($poll->json('distributions'))->firstWhere('platform', $platform)['status']);
        }
        $this->assertSame(['processing', 'processing', 'processing', 'published'], $seen);

        $post = $this->getJson("/api/posts/{$postId}")->assertOk()->assertJsonPath('status', 'published')->json();
        $this->assertNotNull($post['published_at']);
        $this->assertSame('https://i.ytimg.com/vi/'.self::YT_VIDEO.'/hqdefault.jpg', $post['thumbnail'], 'First platform to publish sets the thumbnail.');

        $d = collect($post['distributions'])->keyBy('platform');
        $this->assertSame('https://www.youtube.com/shorts/'.self::YT_VIDEO, $d['youtube']['url']);
        $this->assertSame('private', $d['youtube']['privacy']);
        $this->assertSame('https://www.facebook.com/reel/'.self::FB_VIDEO, $d['facebook']['url']);
        $this->assertSame('https://www.instagram.com/reel/C0ffee123/', $d['instagram']['url']);
        $this->assertSame('SELF_ONLY', $d['tiktok']['privacy']);
        // SELF_ONLY posts have no public id: the link opens the creator profile.
        $this->assertSame('https://www.tiktok.com/@nonso', $d['tiktok']['url']);
        foreach ($d as $platform => $row) {
            $this->assertNull($row['error_message'], $platform);
            $this->assertSame(1, $row['attempts'], $platform);
            $this->assertNotNull($row['published_at'], $platform);
        }

        // What TikTok received honours the form (T-02/T-03).
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/post/publish/video/init/')
            && $r['post_info']['privacy_level'] === 'SELF_ONLY'
            && $r['post_info']['disable_comment'] === true
            && $r['post_info']['disable_duet'] === true
            && $r['post_info']['brand_content_toggle'] === false);
        // Captions carry the hashtags.
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), self::IG_USER.'/media') && str_contains((string) $r['caption'], '#fyp #clips'));

        $this->assertSame([$postId], array_column($this->getJson('/api/posts?filter=published')->json(), 'id'));
        $this->assertSame([], $this->getJson('/api/posts?filter=processing')->json());

        $feed = $this->getJson('/api/notifications?limit=30')->assertOk()->json();
        $this->assertSame(4, $feed['unread_count']);
        $this->assertSame(['distribution_published'], array_values(array_unique(array_column($feed['items'], 'type'))));
        $this->assertSame($postId, $feed['items'][0]['data']['postId']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'exp.host') && $r[0]['to'] === 'ExponentPushToken[abc123]');

        $this->getJson('/api/me')->assertJsonPath('plan.clips_used', 1);
        $this->getJson('/api/analytics/overview?period=7d')->assertJsonPath('posts_published', 1);
    }

    public function test_b2_scheduled_post_waits_then_dispatches(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->user);
        $this->getJson('/api/tiktok/creator-info')->assertOk();

        $at = now()->addHours(2)->toIso8601String();
        $res = $this->createPost(['platforms' => ['youtube', 'tiktok'], 'scheduled_at' => $at])->assertCreated()
            ->assertJsonPath('status', 'scheduled');
        Queue::assertNothingPushed();
        $this->assertSame([$res->json('id')], array_column($this->getJson('/api/posts?filter=scheduled')->json(), 'id'));

        $this->travel(121)->minutes();
        $this->artisan('posts:dispatch-scheduled')->assertSuccessful();
        Queue::assertPushed(PublishToYouTubeShortsJob::class, 1);
        Queue::assertPushed(PublishToTikTokJob::class, 1);
        $this->getJson('/api/posts/'.$res->json('id'))->assertJsonPath('status', 'processing');
    }

    // =========================================================================
    // c. One platform fails, the creator is told, retry succeeds
    // =========================================================================

    public function test_c_instagram_container_error_notifies_and_retry_succeeds(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->user);
        $this->igFinalStatus = 'ERROR';

        $this->getJson('/api/tiktok/creator-info')->assertOk();
        $postId = $this->createPost(['platforms' => ['youtube', 'facebook', 'instagram', 'tiktok']])->assertCreated()->json('id');
        $this->runQueuedPublishJobs();

        $post = $this->getJson("/api/posts/{$postId}")->assertOk()->assertJsonPath('status', 'partial')->json();
        $ig = collect($post['distributions'])->firstWhere('platform', 'instagram');
        $this->assertSame('failed', $ig['status']);
        $this->assertSame('Instagram could not process the video: Error: The video aspect ratio is not supported. Use 9:16.', $ig['error_message']);
        $this->assertNull($ig['url']);

        $this->assertSame([$postId], array_column($this->getJson('/api/posts?filter=failed')->json(), 'id'), 'A partial post is listed under Failed (contract §4).');
        $this->assertSame([$postId], array_column($this->getJson('/api/posts?filter=all')->json(), 'id'));

        $failed = collect($this->getJson('/api/notifications')->json('items'))->firstWhere('type', 'distribution_failed');
        $this->assertNotNull($failed, 'A failure notification is stored for the bell.');
        $this->assertSame('Post failed to publish', $failed['title']);
        $this->assertStringContainsString('Instagram', $failed['body']);
        $this->assertSame($postId, $failed['data']['postId']);
        $this->assertNull($failed['read_at']);

        // Published distributions cannot be retried.
        $yt = collect($post['distributions'])->firstWhere('platform', 'youtube');
        $this->postJson("/api/distributions/{$yt['id']}/retry")->assertStatus(409)
            ->assertExactJson(['message' => 'Only failed distributions can be retried.']);

        // The creator fixes nothing on our side; Instagram processes it this time.
        $this->igFinalStatus = 'FINISHED';
        $this->postJson("/api/distributions/{$ig['id']}/retry")->assertOk()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('error_message', null);
        $this->getJson("/api/posts/{$postId}")->assertJsonPath('status', 'processing');
        Queue::assertPushed(PublishToInstagramReelsJob::class, 2);

        $this->runQueuedPublishJobs();
        $post = $this->getJson("/api/posts/{$postId}")->assertJsonPath('status', 'published')->json();
        $ig = collect($post['distributions'])->firstWhere('platform', 'instagram');
        $this->assertSame('https://www.instagram.com/reel/C0ffee123/', $ig['url']);
        $this->assertSame(2, $ig['attempts']);

        // Another user's distribution is invisible.
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/distributions/{$ig['id']}/retry")->assertNotFound();
    }

    // =========================================================================
    // d. Analytics sync -> every screen shows the same numbers
    // =========================================================================

    public function test_d_analytics_sync_numbers_agree_across_overview_platforms_timeseries_top_posts_post_and_csv(): void
    {
        // Audited TikTok + public post so TikTok returns a real video id (see test_d2 for the unaudited case).
        config(['clippipeline.tiktok_audited' => true]);
        $this->ttPublic = true;
        Queue::fake();
        Sanctum::actingAs($this->user);

        $this->getJson('/api/tiktok/creator-info')->assertOk();
        $postId = $this->createPost(['platforms' => ['youtube', 'facebook', 'instagram', 'tiktok']], 'PUBLIC_TO_EVERYONE')->assertCreated()->json('id');
        $this->runQueuedPublishJobs();
        $this->getJson("/api/posts/{$postId}")->assertJsonPath('status', 'published')
            ->assertJsonPath('distributions.3.url', 'https://www.tiktok.com/@nonso/video/'.self::TT_VIDEO);

        $this->artisan('analytics:sync')->assertSuccessful();

        $expected = [
            'youtube' => ['views' => 500, 'likes' => 40, 'comments' => 5, 'shares' => 3, 'saves' => 0, 'watch_time_seconds' => 3000],
            'facebook' => ['views' => 300, 'likes' => 25, 'comments' => 3, 'shares' => 6, 'saves' => 0, 'watch_time_seconds' => 120],
            'instagram' => ['views' => 900, 'likes' => 70, 'comments' => 9, 'shares' => 4, 'saves' => 2, 'watch_time_seconds' => 4000],
            'tiktok' => ['views' => 1000, 'likes' => 90, 'comments' => 7, 'shares' => 11, 'saves' => 0, 'watch_time_seconds' => 0],
        ];
        $sum = [];
        foreach (array_keys($expected['youtube']) as $k) {
            $sum[$k] = array_sum(array_column($expected, $k));
        }
        $this->assertSame(2700, $sum['views']);

        // Home / overview
        $overview = $this->getJson('/api/analytics/overview?period=7d')->assertOk()->json();
        $this->assertSame($sum, $overview['totals']);
        $this->assertSame(2700, $overview['total_views']);
        $this->assertSame(1, $overview['posts_published']);
        $this->assertSame(1200 + 800 + 3400 + 5000, $overview['followers']['total']);
        $this->assertSame(2700, array_sum(array_column($overview['trend'], 'views')));

        // Insights / platforms
        foreach ($this->getJson('/api/analytics/platforms?period=7d')->assertOk()->json('platforms') as $row) {
            $this->assertSame($expected[$row['platform']], $row['totals'], $row['platform']);
            $this->assertSame(1, $row['posts_published'], $row['platform']);
        }

        // Insights / timeseries, all and per platform (TikTok overlay curve)
        $points = $this->getJson('/api/analytics/timeseries?period=7d&platform=all')->assertOk()->json('points');
        $this->assertSame('2026-10-13', end($points)['date']);
        $this->assertSame(2700, end($points)['views']);
        $this->assertSame(1000, array_sum(array_column($this->getJson('/api/analytics/timeseries?period=7d&platform=tiktok')->json('points'), 'views')));

        // Top posts
        $top = $this->getJson('/api/analytics/top-posts?period=7d&limit=5')->assertOk()->json();
        $this->assertSame($postId, $top[0]['post_id']);
        $this->assertSame($sum, $top[0]['totals']);
        $this->assertNotNull($top[0]['thumbnail']);

        // Post details
        $this->assertSame($sum, $this->getJson("/api/posts/{$postId}")->json('totals'));
        $pa = $this->getJson("/api/posts/{$postId}/analytics")->assertOk()->json();
        $this->assertSame($sum, $pa['totals']);
        foreach ($pa['platforms'] as $row) {
            $this->assertSame($expected[$row['platform']], $row['metrics'], $row['platform']);
            $this->assertSame('2026-10-13', $row['captured_on']);
        }

        // Me badge now "active"
        $this->getJson('/api/me')->assertJsonPath('sync.status', 'active');

        // Export CSV
        $csv = $this->get('/api/analytics/export?period=7d')->assertOk();
        $this->assertSame('attachment; filename="clippipeline-analytics-7d.csv"', $csv->headers->get('Content-Disposition'));
        $lines = array_values(array_filter(explode("\n", $csv->getContent())));
        $this->assertCount(5, $lines);
        $header = str_getcsv($lines[0], escape: '');
        $csvTotals = array_fill_keys(array_keys($sum), 0);
        foreach (array_slice($lines, 1) as $line) {
            $row = array_combine($header, str_getcsv($line, escape: ''));
            foreach (array_keys($sum) as $k) {
                $this->assertSame($expected[$row['platform']][$k], (int) $row[$k], "csv {$row['platform']}.$k");
                $csvTotals[$k] += (int) $row[$k];
            }
            $this->assertNotSame('', $row['url']);
        }
        $this->assertSame($sum, $csvTotals);

        // Next day: growth only counts the gain; lifetime on the post keeps rising.
        $this->travel(1)->days();
        $this->artisan('analytics:sync')->assertSuccessful();
        $this->assertSame(2700, $this->getJson('/api/analytics/overview?period=7d')->json('totals.views'),
            'Same lifetime counters a day later add 0 views, total stays 2700.');
    }

    public function test_d3_csv_export_for_a_period_matches_the_insights_screen_for_older_posts(): void
    {
        Sanctum::actingAs($this->user);
        // A clip published 10 days ago that keeps getting views this week.
        $post = Post::factory()->upload()->create(['user_id' => $this->user->id, 'title' => 'Older clip']);
        $d = PostDistribution::create(['post_id' => $post->id, 'platform' => 'youtube', 'status' => 'published',
            'external_id' => self::YT_VIDEO, 'published_at' => now()->subDays(10)]);
        PostAnalytic::factory()->forDistribution($d, now()->subDays(8)->toDateString())->create(['views' => 1000, 'likes' => 10, 'comments' => 0, 'shares' => 0, 'saves' => 0, 'watch_time_seconds' => 0]);
        PostAnalytic::factory()->forDistribution($d, now()->toDateString())->create(['views' => 1500, 'likes' => 15, 'comments' => 0, 'shares' => 0, 'saves' => 0, 'watch_time_seconds' => 0]);

        $screen = $this->getJson('/api/analytics/overview?period=7d')->assertOk()->json('totals.views');
        $this->assertSame(500, $screen, 'Insights 7d shows the 500 views gained this week.');

        $lines = array_values(array_filter(explode("\n", $this->get('/api/analytics/export?period=7d')->assertOk()->getContent())));
        $csvViews = 0;
        foreach (array_slice($lines, 1) as $line) {
            $csvViews += (int) str_getcsv($line, escape: '')[5];
        }

        // BUG-2 fixed: the export uses the same window math as Insights.
        $this->assertSame($screen, $csvViews);
    }

    public function test_d4_connect_tab_synced_date_moves_after_analytics_sync(): void
    {
        Sanctum::actingAs($this->user);
        SocialAccount::query()->update(['last_synced_at' => now()->subDays(10)]);

        $this->artisan('analytics:sync')->assertSuccessful();

        $this->getJson('/api/me')->assertJsonPath('sync.status', 'active'); // follower snapshots were written
        $this->assertSame([1200, 800, 3400, 5000], array_column($this->getJson('/api/social-accounts')->json(), 'follower_count'));

        $stale = collect($this->getJson('/api/social-accounts')->json())
            ->filter(fn ($a) => CarbonImmutable::parse($a['last_synced_at'])->lt(now()->subDay()))
            ->pluck('platform')->all();
        // BUG-3 fixed: analytics:sync stamps last_synced_at.
        $this->assertSame([], $stale);
    }

    public function test_d2_unaudited_tiktok_private_post_has_no_public_id_so_analytics_stay_zero(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->user);
        $this->getJson('/api/tiktok/creator-info')->assertOk();
        $postId = $this->createPost(['platforms' => ['tiktok']])->assertCreated()->json('id');
        $this->runQueuedPublishJobs();

        $this->artisan('analytics:sync')->assertSuccessful();

        $dist = PostDistribution::where('post_id', $postId)->sole();
        $this->assertSame(self::TT_PUBLISH, $dist->external_id, 'The publish_id stands in for the video id.');
        $this->assertSame(0, PostAnalytic::where('distribution_id', $dist->id)->count());
        $this->assertSame(0, $this->getJson('/api/analytics/overview?period=7d')->json('totals.views'));
    }

    // =========================================================================
    // e. Validation a creator actually hits
    // =========================================================================

    public function test_e_validation_messages_are_clear(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->user);
        $this->getJson('/api/tiktok/creator-info')->assertOk();

        // Facebook clip longer than 90 s.
        $this->createPost(['platforms' => ['facebook'], 'video' => $this->mp4(120), 'video_duration_seconds' => '120'])
            ->assertStatus(422)
            ->assertJsonPath('errors.video.0', 'Facebook Reels must be 3 to 90 seconds long.');

        // TikTok privacy that creator_info did not offer (unaudited => SELF_ONLY only).
        $this->assertError($this->createPost(['platforms' => ['tiktok']], 'PUBLIC_TO_EVERYONE'),
            'platform_options.tiktok.privacy_level', 'Choose one of the privacy options TikTok offers for this account.');

        // Music consent unticked (the app sends 0).
        $this->assertError($this->createPost(['platforms' => ['tiktok']], 'SELF_ONLY', ['music_consent' => '0']),
            'platform_options.tiktok.music_consent', "You must agree to TikTok's Music Usage Confirmation.");

        // Branded content + Only me (T-05/T-09).
        $this->assertError($this->createPost(['platforms' => ['tiktok']], 'SELF_ONLY', ['brand_content_toggle' => '1']),
            'platform_options.tiktok.privacy_level', 'Branded content visibility cannot be set to private.');

        // YouTube guidelines unticked.
        $this->assertError($this->createPost(['platforms' => ['youtube'], 'yt_guidelines' => '0']),
            'platform_options.youtube.guidelines_confirmed', "Confirm the video follows YouTube's Community Guidelines.");

        // Scheduling in the past.
        $past = $this->createPost(['platforms' => ['youtube'], 'scheduled_at' => now()->subHour()->toIso8601String()])
            ->assertStatus(422)->assertJsonValidationErrors(['scheduled_at']);
        $this->assertSame('The scheduled at field must be a date after now.', $past->json('errors.scheduled_at.0'));

        // A platform that is not connected (TikTok needs re-auth).
        $this->accounts['tiktok']->forceFill(['status' => 'needs_reauth', 'last_error' => 'Token revoked.'])->save();
        $this->assertError($this->createPost(['platforms' => ['youtube', 'tiktok']]),
            'platforms.1', 'Connect an active tiktok account first.');

        // Nothing was stored or queued by any rejected attempt.
        $this->assertSame(0, Post::count());
        // (No allFiles() check: other agents' test runs share storage/framework/testing/disks/public.)
        Queue::assertNothingPushed();
    }

    // =========================================================================
    // f. Account management
    // =========================================================================

    public function test_f_disconnect_reconnect_preferences_profile_notifications_delete_post_delete_account(): void
    {
        Queue::fake();
        $token = $this->user->createToken('android')->plainTextToken;
        $api = fn () => $this->withToken($token);

        // Seed a published post with analytics + notifications via a real publish.
        $api()->getJson('/api/tiktok/creator-info')->assertOk();
        $postId = $api()->post('/api/posts', $this->postForm(['platforms' => ['youtube', 'tiktok']]), ['Accept' => 'application/json'])->assertCreated()->json('id');
        $this->runQueuedPublishJobs();
        $this->assertSame(2, $api()->getJson('/api/notifications')->json('unread_count'));

        // --- Disconnect TikTok: revoked at TikTok, rows and its analytics gone.
        $tiktokId = $this->accounts['tiktok']->id;
        $api()->deleteJson("/api/social-accounts/{$tiktokId}")->assertOk()->assertExactJson(['deleted_ids' => [$tiktokId]]);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://open.tiktokapis.com/v2/oauth/revoke/' && $r['token'] === 'tok-tiktok' && $r['client_key'] === 'tkey');
        $this->assertDatabaseMissing('social_accounts', ['id' => $tiktokId]);
        $this->assertSame(['youtube', 'facebook', 'instagram'], array_column($api()->getJson('/api/social-accounts')->json(), 'platform'));
        $api()->getJson('/api/tiktok/creator-info')->assertNotFound();
        $api()->deleteJson("/api/social-accounts/{$tiktokId}")->assertNotFound(); // double tap

        // --- Reconnect start URL (mobile).
        $start = $api()->getJson('/api/connect/tiktok/start?platform=mobile')->assertOk()
            ->assertJsonPath('provider', 'tiktok')->assertJsonPath('platform', 'mobile')->json();
        $this->assertStringStartsWith('https://www.tiktok.com/v2/auth/authorize', $start['auth_url']);
        $this->assertStringContainsString(urlencode('https://clip.test/integrations/callback/tiktok'), $start['auth_url']);
        $this->assertStringContainsString('video.publish', urldecode($start['auth_url']));
        $this->assertStringNotContainsString('video.upload', urldecode($start['auth_url']));
        $api()->getJson('/api/connect/myspace/start?platform=mobile')->assertStatus(422);
        $meta = $api()->getJson('/api/connect/meta/start?platform=mobile')->assertOk()->json('auth_url');
        $this->assertStringContainsString('instagram_content_publish', urldecode($meta));

        // --- Preferences.
        $api()->patchJson('/api/me/preferences', ['auto_sync' => false, 'default_platforms' => ['youtube', 'instagram'], 'default_hashtags' => ['fyp', 'nonso']])
            ->assertOk()
            ->assertJsonPath('auto_sync', false)
            ->assertJsonPath('default_platforms', ['youtube', 'instagram'])
            ->assertJsonPath('default_hashtags', ['fyp', 'nonso'])
            ->assertJsonPath('notifications_enabled', true);
        $api()->getJson('/api/me')->assertJsonPath('preferences.auto_sync', false);
        $api()->patchJson('/api/me/preferences', ['default_platforms' => ['myspace']])->assertStatus(422);

        // --- Edit name.
        $api()->patchJson('/api/me', ['name' => 'Nonso Okafor'])->assertOk()->assertJsonPath('user.name', 'Nonso Okafor');
        $api()->patchJson('/api/me', ['name' => ''])->assertStatus(422)->assertJsonValidationErrors(['name']);

        // --- Notifications read-all.
        $api()->postJson('/api/notifications/read-all')->assertNoContent();
        $feed = $api()->getJson('/api/notifications')->json();
        $this->assertSame(0, $feed['unread_count']);
        $this->assertNotContains(null, array_column($feed['items'], 'read_at'));

        // --- Delete post: local only.
        $videoPath = Post::findOrFail($postId)->video_path;
        Storage::disk('public')->assertExists($videoPath);
        $api()->deleteJson("/api/posts/{$postId}")->assertNoContent();
        $api()->getJson("/api/posts/{$postId}")->assertNotFound();
        Storage::disk('public')->assertMissing($videoPath);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), 'googleapis'));

        // --- Delete account: typed confirmation, tokens revoked, everything gone.
        $api()->deleteJson('/api/me', ['confirm' => 'yes'])->assertStatus(422);
        $second = $api()->post('/api/posts', $this->postForm(['platforms' => ['youtube']]), ['Accept' => 'application/json'])->assertCreated()->json('id');
        $secondPath = Post::findOrFail($second)->video_path;

        $del = $api()->deleteJson('/api/me', ['confirm' => 'DELETE'])->assertStatus(202)->assertJsonPath('status', 'scheduled');
        $this->assertTrue(CarbonImmutable::parse($del->json('completes_by'))->lte(now()->addDays(7)));
        Http::assertSent(fn (Request $r) => $r->url() === 'https://oauth2.googleapis.com/revoke' && $r['token'] === 'rt-youtube');
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/'.self::FB_USER.'/permissions'));
        $this->assertSame(0, SocialAccount::where('user_id', $this->user->id)->count());
        $this->assertSame(0, DeviceToken::where('user_id', $this->user->id)->count());
        $this->assertSame(0, $this->user->tokens()->count());

        // The bearer token stops working immediately.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();

        Queue::assertPushed(PurgeUserDataJob::class, 1);
        Queue::pushed(PurgeUserDataJob::class)->each(fn ($job) => app()->call([$job, 'handle']));

        $this->assertNull(User::find($this->user->id));
        $this->assertSame(0, Post::where('user_id', $this->user->id)->count());
        $this->assertNull(Post::find($second));
        $this->assertSame(0, PostDistribution::count());
        $this->assertSame(0, PostAnalytic::count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $this->user->id)->count());
        Storage::disk('public')->assertMissing($secondPath);
    }

    // =========================================================================
    // g. Mobile sign-in broker
    // =========================================================================

    public function test_g2_google_sign_in_links_a_creator_who_signed_up_with_email_first(): void
    {
        // The creator registered with email/password (or Facebook) and now taps "Continue with Google".
        $this->assertNull($this->user->google_id);
        $state = $this->stateFrom($this->getJson('/api/auth/google/start?platform=mobile')->json('auth_url'));
        $location = (string) $this->get('/auth/callback/google?'.http_build_query(['code' => 'google-code', 'state' => $state]))->headers->get('Location');

        // BUG-1 fixed: Google links the existing account by verified email.
        $this->assertStringStartsWith('clippipelinemobile://auth?status=ok&code=', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $back);
        $this->postJson('/api/auth/exchange', ['code' => $back['code'], 'device_name' => 'android'])->assertOk()
            ->assertJsonPath('user.id', $this->user->id);
        $this->assertSame(1, User::count());
    }

    public function test_g_google_sign_in_broker_returns_the_existing_creator_once(): void
    {
        // Returning creator who originally signed up with Google.
        $this->user->forceFill(['google_id' => 'google-sub-1'])->save();

        $start = $this->getJson('/api/auth/google/start?platform=mobile')->assertOk()->assertJsonPath('provider', 'google')->json();
        $this->assertStringStartsWith('https://accounts.google.com/', $start['auth_url']);
        parse_str((string) parse_url($start['auth_url'], PHP_URL_QUERY), $q);
        $this->assertSame('https://clip.test/auth/callback/google', $q['redirect_uri']);
        $this->assertSame('login', app(OAuthState::class)->decodeLogin($q['state'])['purpose']);

        // Google sends the browser back to our existing callback.
        $location = $this->get('/auth/callback/google?'.http_build_query(['code' => 'google-code', 'state' => $q['state']]))
            ->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('clippipelinemobile://auth?status=ok&code=', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $back);

        $res = $this->postJson('/api/auth/exchange', ['code' => $back['code'], 'device_name' => 'android'])->assertOk()
            ->assertJsonPath('user.id', $this->user->id)   // same creator, matched by email
            ->assertJsonPath('user.email', 'nonso@example.com');
        $this->assertSame(1, User::count(), 'No duplicate user for a returning creator.');

        $this->withToken($res->json('token'))->getJson('/api/me')->assertOk()->assertJsonPath('user.id', $this->user->id);
        $this->withToken($res->json('token'))->getJson('/api/social-accounts')->assertOk()->assertJsonCount(4);

        // Single use.
        $this->postJson('/api/auth/exchange', ['code' => $back['code'], 'device_name' => 'android'])
            ->assertStatus(422)->assertJsonValidationErrors(['code']);

        // User cancels at Google -> app gets a readable error.
        $state2 = $this->stateFrom($this->getJson('/api/auth/google/start')->json('auth_url'));
        $loc = $this->get('/auth/callback/google?'.http_build_query(['error' => 'access_denied', 'state' => $state2]))->headers->get('Location');
        $this->assertStringStartsWith('clippipelinemobile://auth?status=error&message=', $loc);
    }

    // =========================================================================
    // helpers
    // =========================================================================

    private function assertError($res, string $key, string $message): void
    {
        $res->assertStatus(422);
        $errors = (array) $res->json('errors');
        $this->assertArrayHasKey($key, $errors, '422 keys: '.implode(', ', array_keys($errors)));
        $this->assertSame($message, $errors[$key][0]);
    }

    private function stateFrom(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        return $q['state'];
    }

    private function mp4(float $seconds): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'mp4');
        file_put_contents($path, Mp4Duration::fixture($seconds));

        return new UploadedFile($path, 'clip.mp4', 'video/mp4', null, true);
    }

    /** Multipart body exactly as buildPostForm() in src/api/hooks.ts builds it. */
    private function postForm(array $over = [], string $ttPrivacy = 'SELF_ONLY', array $ttOver = []): array
    {
        $platforms = $over['platforms'] ?? ['youtube'];
        $form = [
            'video' => $over['video'] ?? $this->mp4(30),
            'title' => 'Sunset timelapse',
            'caption' => 'Lagos sunset in 30 seconds',
            'hashtags' => ['fyp', 'clips'],
            'platforms' => $platforms,
            'video_duration_seconds' => $over['video_duration_seconds'] ?? '30',
        ];
        if (isset($over['scheduled_at'])) {
            $form['scheduled_at'] = $over['scheduled_at'];
        }
        if (in_array('youtube', $platforms, true)) {
            $form['platform_options']['youtube'] = ['privacy_status' => 'public', 'guidelines_confirmed' => $over['yt_guidelines'] ?? '1'];
        }
        if (in_array('tiktok', $platforms, true)) {
            $form['platform_options']['tiktok'] = $ttOver + [
                'privacy_level' => $ttPrivacy,
                'disable_comment' => '1', 'disable_duet' => '1', 'disable_stitch' => '1',
                'brand_organic_toggle' => '0', 'brand_content_toggle' => '0', 'is_aigc' => '0',
                'music_consent' => '1',
            ];
        }

        return $form;
    }

    private function createPost(array $over = [], string $ttPrivacy = 'SELF_ONLY', array $ttOver = [])
    {
        return $this->post('/api/posts', $this->postForm($over, $ttPrivacy, $ttOver), ['Accept' => 'application/json']);
    }

    /**
     * Act as the queue worker: run every pushed publish job once, in push order.
     *
     * @return array<int, string> platforms run, in order
     */
    private function runQueuedPublishJobs(?string $only = null): array
    {
        $order = [
            'youtube' => PublishToYouTubeShortsJob::class,
            'facebook' => PublishToFacebookReelsJob::class,
            'instagram' => PublishToInstagramReelsJob::class,
            'tiktok' => PublishToTikTokJob::class,
        ];
        $done = [];
        foreach ($order as $platform => $class) {
            if ($only !== null && $only !== $platform) {
                continue;
            }
            foreach (Queue::pushed($class) as $job) {
                $key = spl_object_id($job);
                if (isset($this->ran[$key])) {
                    continue;
                }
                $this->ran[$key] = true;
                $job->handle();
                // A real worker releases the ShouldBeUnique lock when the job finishes.
                (new UniqueLock(app('cache')->store()))->release($job);
                $done[] = $platform;
            }
        }

        return $done;
    }

    /** One router for every outbound call, answering like the real platform. */
    private function route(Request $r)
    {
        $url = $r->url();
        $host = (string) parse_url($url, PHP_URL_HOST);
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $m = $r->method();

        // Our own public storage (HEAD reachability check).
        if ($host === 'clip.test') {
            return Http::response('', 200);
        }
        if ($host === 'exp.host') {
            return Http::response(['data' => [['status' => 'ok', 'id' => 'receipt-1']]]);
        }

        // --- Google / YouTube -------------------------------------------------
        if ($host === 'oauth2.googleapis.com' && $path === '/revoke') {
            return Http::response('', 200);
        }
        if ($host === 'oauth2.googleapis.com' && $path === '/token') {
            return Http::response(['access_token' => 'g-login-at', 'expires_in' => 3599, 'id_token' => 'x']);
        }
        if ($host === 'openidconnect.googleapis.com') {
            return Http::response(['sub' => 'google-sub-1', 'email' => 'nonso@example.com', 'email_verified' => true, 'name' => 'Nonso Creator', 'picture' => 'https://lh3.test/a.png']);
        }
        if ($host === 'www.googleapis.com' && $path === '/upload/youtube/v3/videos') {
            return Http::response('', 200, ['Location' => 'https://upload.youtube.test/session/abc']);
        }
        if ($host === 'upload.youtube.test') {
            return Http::response([
                'id' => self::YT_VIDEO,
                'status' => ['privacyStatus' => 'private', 'uploadStatus' => 'uploaded'],
                'snippet' => ['thumbnails' => ['high' => ['url' => 'https://i.ytimg.com/vi/'.self::YT_VIDEO.'/hqdefault.jpg']]],
            ], 201);
        }
        if ($host === 'www.googleapis.com' && $path === '/youtube/v3/videos') {
            return Http::response(['items' => [['id' => self::YT_VIDEO, 'statistics' => ['viewCount' => '500', 'likeCount' => '40', 'commentCount' => '5']]]]);
        }
        if ($host === 'youtubeanalytics.googleapis.com') {
            return Http::response([
                'columnHeaders' => [['name' => 'video'], ['name' => 'estimatedMinutesWatched'], ['name' => 'averageViewDuration'], ['name' => 'shares']],
                'rows' => [[self::YT_VIDEO, 50, 6, 3]],
            ]);
        }
        if ($host === 'www.googleapis.com' && $path === '/youtube/v3/channels') {
            return Http::response(['items' => [['statistics' => ['subscriberCount' => '1200']]]]);
        }

        // --- TikTok -------------------------------------------------------------
        if ($host === 'open.tiktokapis.com') {
            $ok = ['code' => 'ok', 'message' => '', 'log_id' => 'log1'];
            switch ($path) {
                case '/v2/post/publish/creator_info/query/':
                    return Http::response(['data' => [
                        'creator_avatar_url' => 'https://p16.tiktokcdn.test/avatar.jpg', 'creator_username' => 'nonso', 'creator_nickname' => 'Nonso',
                        'privacy_level_options' => $this->creatorOptions, 'comment_disabled' => false, 'duet_disabled' => false,
                        'stitch_disabled' => false, 'max_video_post_duration_sec' => 600,
                    ], 'error' => $ok]);
                case '/v2/post/publish/video/init/':
                    return Http::response(['data' => ['publish_id' => self::TT_PUBLISH, 'upload_url' => 'https://open-upload.tiktokapis.com/video/?upload_id=1&upload_token=t'], 'error' => $ok]);
                case '/v2/post/publish/status/fetch/':
                    $this->ttPolls++;
                    if ($this->ttPolls === 1) {
                        return Http::response(['data' => ['status' => 'PROCESSING_UPLOAD', 'uploaded_bytes' => 100], 'error' => $ok]);
                    }

                    return Http::response(['data' => ['status' => 'PUBLISH_COMPLETE'] + ($this->ttPublic ? ['publicaly_available_post_id' => [(int) self::TT_VIDEO]] : []), 'error' => $ok]);
                case '/v2/video/query/':
                    $videos = [];
                    foreach ((array) ($r['filters']['video_ids'] ?? []) as $id) {
                        if ($this->ttPublic && (string) $id === self::TT_VIDEO) {
                            $videos[] = ['id' => self::TT_VIDEO, 'cover_image_url' => 'https://p16.tiktokcdn.test/cover.jpg',
                                'share_url' => 'https://www.tiktok.com/@nonso/video/'.self::TT_VIDEO,
                                'view_count' => 1000, 'like_count' => 90, 'comment_count' => 7, 'share_count' => 11];
                        }
                    }

                    return Http::response(['data' => ['videos' => $videos, 'cursor' => 0, 'has_more' => false], 'error' => $ok]);
                case '/v2/user/info/':
                    return Http::response(['data' => ['user' => ['open_id' => 'open-id-nonso', 'display_name' => 'Nonso', 'follower_count' => 5000]], 'error' => $ok]);
                case '/v2/oauth/revoke/':
                    return Http::response('', 200);
            }
        }
        if ($host === 'open-upload.tiktokapis.com') {
            return Http::response('', 201);
        }

        // --- Meta ---------------------------------------------------------------
        if ($host === 'rupload.facebook.com') {
            return Http::response(['success' => true]);
        }
        if ($host === 'graph.facebook.com' && str_starts_with($path, '/'.self::G.'/')) {
            $node = substr($path, strlen('/'.self::G.'/'));
            $fields = (string) ($q['fields'] ?? '');

            switch (true) {
                case $node === self::PAGE_ID.'/video_reels' && $m === 'POST':
                    return ($r['upload_phase'] ?? null) === 'start'
                        ? Http::response(['video_id' => self::FB_VIDEO, 'upload_url' => 'https://rupload.facebook.com/video-upload/'.self::G.'/'.self::FB_VIDEO])
                        : Http::response(['success' => true]);
                case $node === self::FB_VIDEO && $fields === 'status':
                    $this->fbPolls++;

                    return Http::response(['status' => $this->fbPolls === 1
                        ? ['video_status' => 'upload_complete', 'uploading_phase' => ['status' => 'complete'], 'publishing_phase' => ['status' => 'not_started']]
                        : ['video_status' => 'ready', 'uploading_phase' => ['status' => 'complete'], 'processing_phase' => ['status' => 'complete'], 'publishing_phase' => ['status' => 'complete', 'publish_status' => 'published']],
                    ]);
                case $node === self::FB_VIDEO && str_contains($fields, 'permalink_url'):
                    return Http::response(['permalink_url' => '/reel/'.self::FB_VIDEO, 'picture' => 'https://scontent.xx.fbcdn.test/reel.jpg', 'post_id' => self::PAGE_ID.'_555', 'id' => self::FB_VIDEO]);
                case $node === self::FB_VIDEO.'/video_insights':
                    return Http::response(['data' => [
                        ['name' => 'blue_reels_play_count', 'values' => [['value' => 300]]],
                        ['name' => 'post_video_view_time', 'values' => [['value' => 120000]]],
                        ['name' => 'post_video_avg_time_watched', 'values' => [['value' => 4000]]],
                        ['name' => 'post_video_social_actions', 'values' => [['value' => ['SHARE' => 6, 'COMMENT' => 3]]]],
                        ['name' => 'post_video_likes_by_reaction_type', 'values' => [['value' => ['REACTION_LIKE' => 20, 'REACTION_LOVE' => 5]]]],
                    ]]);
                case $node === self::FB_VIDEO:
                    return Http::response(['id' => self::FB_VIDEO, 'likes' => ['summary' => ['total_count' => 22]], 'comments' => ['summary' => ['total_count' => 3]]]);
                case $node === self::PAGE_ID:
                    return Http::response(['id' => self::PAGE_ID, 'followers_count' => 800, 'fan_count' => 750]);

                case $node === self::IG_USER.'/content_publishing_limit':
                    return Http::response(['data' => [['quota_usage' => 3, 'config' => ['quota_total' => 100, 'quota_duration' => 86400]]]]);
                case $node === self::IG_USER.'/media' && $m === 'POST':
                    $this->igPolls = 0;

                    return Http::response(['id' => self::IG_CONTAINER]);
                case $node === self::IG_CONTAINER:
                    $this->igPolls++;

                    return Http::response($this->igPolls === 1
                        ? ['status_code' => 'IN_PROGRESS', 'status' => 'In Progress: Media is still being processed.', 'id' => self::IG_CONTAINER]
                        : ['status_code' => $this->igFinalStatus, 'status' => $this->igFinalStatus === 'ERROR' ? 'Error: The video aspect ratio is not supported. Use 9:16.' : 'Finished: Media has been uploaded and it is complete.', 'id' => self::IG_CONTAINER]);
                case $node === self::IG_USER.'/media_publish' && $m === 'POST':
                    return Http::response(['id' => self::IG_MEDIA]);
                case $node === self::IG_MEDIA.'/insights':
                    return Http::response(['data' => [
                        ['name' => 'views', 'values' => [['value' => 900]]],
                        ['name' => 'likes', 'values' => [['value' => 70]]],
                        ['name' => 'comments', 'values' => [['value' => 9]]],
                        ['name' => 'shares', 'values' => [['value' => 4]]],
                        ['name' => 'saved', 'values' => [['value' => 2]]],
                        ['name' => 'ig_reels_avg_watch_time', 'values' => [['value' => 4500]]],
                        ['name' => 'ig_reels_video_view_total_time', 'values' => [['value' => 4000000]]],
                    ]]);
                case $node === self::IG_MEDIA:
                    return Http::response(['permalink' => 'https://www.instagram.com/reel/C0ffee123/', 'thumbnail_url' => 'https://scontent.cdninstagram.test/thumb.jpg', 'id' => self::IG_MEDIA]);
                case $node === self::IG_USER:
                    return Http::response(['id' => self::IG_USER, 'followers_count' => 3400]);

                case $node === self::FB_USER.'/permissions' && $m === 'DELETE':
                    return Http::response(['success' => true]);
                case $node === 'debug_token':
                    return Http::response(['data' => ['user_id' => self::FB_USER, 'is_valid' => true]]);
            }
        }

        $this->unexpected[] = $m.' '.$url;

        return Http::response(['error' => ['message' => 'USER-SIM router: unexpected call']], 599);
    }
}
