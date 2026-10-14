<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostAnalytic;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** CONTRACT §13 (T-01/T-08) and §14. */
class CreatorInfoAndExportTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'open.tiktokapis.com/v2/post/publish/creator_info/query/';

    private function tiktokUser(): User
    {
        $user = User::factory()->create();
        SocialAccount::factory()->platform('tiktok')->create(['user_id' => $user->id, 'handle' => '@nonso', 'display_name' => 'Nonso', 'expires_at' => now()->addDay()]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_creator_info_filters_to_self_only_while_unaudited(): void
    {
        $this->tiktokUser();
        Http::fake([self::URL => Http::response(['data' => [
            'creator_avatar_url' => 'https://a', 'creator_username' => 'nonso', 'creator_nickname' => 'Nonso',
            'privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'SELF_ONLY'],
            'comment_disabled' => false, 'duet_disabled' => true, 'stitch_disabled' => false, 'max_video_post_duration_sec' => 600,
        ], 'error' => ['code' => 'ok']])]);
        Http::preventStrayRequests();

        $this->getJson('/api/tiktok/creator-info')->assertOk()->assertExactJson([
            'creator_nickname' => 'Nonso', 'creator_username' => 'nonso', 'creator_avatar_url' => 'https://a',
            'privacy_level_options' => ['SELF_ONLY'], 'comment_disabled' => false, 'duet_disabled' => true,
            'stitch_disabled' => false, 'max_video_post_duration_sec' => 600, 'can_post' => true, 'reason' => null, 'audited' => false,
        ]);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r->hasHeader('Authorization'));

        config(['clippipeline.tiktok_audited' => true]);
        $this->getJson('/api/tiktok/creator-info')
            ->assertJsonPath('privacy_level_options', ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'SELF_ONLY'])
            ->assertJsonPath('audited', true);
    }

    public function test_creator_info_cannot_post(): void
    {
        $this->tiktokUser();
        Http::fake([self::URL => Http::response(['data' => [], 'error' => ['code' => 'spam_risk_too_many_posts', 'message' => 'x']])]);

        $this->getJson('/api/tiktok/creator-info')->assertOk()
            ->assertJsonPath('can_post', false)
            ->assertJsonPath('creator_nickname', 'Nonso')
            ->assertJsonPath('reason', 'This TikTok account has reached its posting limit for now. Try again later.');
    }

    public function test_creator_info_404_without_active_tiktok(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/tiktok/creator-info')->assertNotFound()->assertJsonPath('message', 'Not found.');

        SocialAccount::factory()->platform('tiktok')->needsReauth()->create(['user_id' => $user->id]);
        $this->getJson('/api/tiktok/creator-info')->assertNotFound();

        // Another user's TikTok does not count.
        SocialAccount::factory()->platform('tiktok')->create();
        $this->getJson('/api/tiktok/creator-info')->assertNotFound();
    }

    public function test_export_csv(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $post = Post::factory()->upload()->create(['user_id' => $user->id, 'title' => '=cmd() Clip']);
        $dist = PostDistribution::create(['post_id' => $post->id, 'platform' => 'youtube', 'status' => 'published', 'external_id' => 'V1', 'published_at' => now()->subDays(2)]);
        PostAnalytic::factory()->forDistribution($dist, now()->subDay()->toDateString())->create(['views' => 10]);
        PostAnalytic::factory()->forDistribution($dist, now()->toDateString())->create(['views' => 25, 'likes' => 3, 'comments' => 1, 'shares' => 0, 'saves' => 2, 'watch_time_seconds' => 90]);
        // Old (outside 7d) and another user's rows are excluded.
        PostDistribution::create(['post_id' => $post->id, 'platform' => 'tiktok', 'status' => 'published', 'external_id' => 'T1', 'published_at' => now()->subDays(20)]);
        $foreign = Post::factory()->upload()->create();
        PostDistribution::create(['post_id' => $foreign->id, 'platform' => 'youtube', 'status' => 'published', 'external_id' => 'X', 'published_at' => now()]);

        $res = $this->get('/api/analytics/export')->assertOk();
        $this->assertStringStartsWith('text/csv', $res->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="clippipeline-analytics-7d.csv"', $res->headers->get('Content-Disposition'));

        $lines = array_values(array_filter(explode("\n", $res->getContent())));
        $this->assertSame('post_id,title,platform,url,published_at,views,likes,comments,shares,saves,watch_time_seconds,captured_on', $lines[0]);
        $this->assertCount(2, $lines);
        $row = str_getcsv($lines[1], escape: '');
        $this->assertSame([(string) $post->id, "'=cmd() Clip", 'youtube'], array_slice($row, 0, 3));
        $this->assertSame(['25', '3', '1', '0', '2', '90', now()->toDateString()], array_slice($row, 5));

        $this->assertCount(3, array_filter(explode("\n", $this->get('/api/analytics/export?period=30d')->getContent())));
        $this->get('/api/analytics/export?period=30d')->assertHeader('Content-Disposition', 'attachment; filename="clippipeline-analytics-30d.csv"');
        $this->getJson('/api/analytics/export?period=1y')->assertStatus(422);
    }
}
