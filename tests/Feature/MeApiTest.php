<?php

namespace Tests\Feature;

use App\Models\AccountFollowerSnapshot;
use App\Models\DataDeletionRequest;
use App\Models\DeviceToken;
use App\Models\Post;
use App\Models\PostAnalytic;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Account\PreferencesService;
use App\Services\Analytics\AnalyticsSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** CONTRACT §10. */
class MeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_full_shape(): void
    {
        $user = User::factory()->create(['name' => 'Ada']);
        $user->forceFill(['settings' => ['billing' => ['plan' => 'creator_pro', 'status' => 'active', 'renews_at' => '2026-11-14T00:00:00+00:00']]])->save();
        Post::factory()->upload()->create(['user_id' => $user->id]);
        Post::factory()->upload()->create(['user_id' => $user->id, 'created_at' => now()->subMonths(2)]);
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/me')->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'avatar'], 'server_time',
                'plan' => ['name', 'clips_used', 'clips_limit', 'renews_at', 'billing_url'],
                'sync' => ['status', 'last_synced_at'],
                'preferences' => ['notifications_enabled', 'auto_sync', 'default_platforms', 'default_hashtags'],
                'flags' => ['tiktok_audited', 'youtube_audited', 'support_email', 'privacy_url', 'terms_url', 'delete_account_url'],
            ])
            ->assertJsonPath('user.name', 'Ada')
            ->assertJsonPath('plan.name', 'pro')
            ->assertJsonPath('plan.clips_used', 1)
            ->assertJsonPath('plan.clips_limit', 20)
            ->assertJsonPath('sync.status', 'idle')
            ->assertJsonPath('preferences.notifications_enabled', true)
            ->assertJsonPath('preferences.default_platforms', [])
            ->assertJsonPath('flags.tiktok_audited', false);

        $this->assertStringStartsWith('http', $res->json('plan.billing_url'));
        $this->assertStringEndsWith('/delete-account', $res->json('flags.delete_account_url'));
    }

    public function test_sync_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $account = SocialAccount::factory()->platform('youtube')->create(['user_id' => $user->id]);

        $this->getJson('/api/me')->assertJsonPath('sync.status', 'idle');

        AccountFollowerSnapshot::create(['social_account_id' => $account->id, 'platform' => 'youtube', 'followers' => 5, 'captured_on' => now()->toDateString()]);
        $this->getJson('/api/me')->assertJsonPath('sync.status', 'active');

        SocialAccount::factory()->platform('tiktok')->needsReauth()->create(['user_id' => $user->id]);
        $this->getJson('/api/me')->assertJsonPath('sync.status', 'attention');
    }

    public function test_patch_me_and_preferences(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/me', ['name' => ''])->assertStatus(422)->assertJsonValidationErrors(['name']);
        $this->patchJson('/api/me', ['name' => str_repeat('a', 101)])->assertStatus(422);
        $this->patchJson('/api/me', ['name' => 'New name'])->assertOk()->assertJsonPath('user.name', 'New name');

        $this->getJson('/api/me/preferences')->assertOk()->assertExactJson([
            'notifications_enabled' => true, 'auto_sync' => true, 'default_platforms' => [], 'default_hashtags' => [],
        ]);

        $this->patchJson('/api/me/preferences', ['default_platforms' => ['myspace']])->assertStatus(422)
            ->assertJsonValidationErrors(['default_platforms.0']);

        $this->patchJson('/api/me/preferences', ['auto_sync' => false, 'default_platforms' => ['tiktok', 'youtube'], 'default_hashtags' => ['#fyp', 'clips']])
            ->assertOk()
            ->assertJsonPath('auto_sync', false)
            ->assertJsonPath('notifications_enabled', true)
            ->assertJsonPath('default_platforms', ['youtube', 'tiktok'])
            ->assertJsonPath('default_hashtags', ['fyp', 'clips']);

        $this->assertSame([$user->id], PreferencesService::autoSyncDisabledUserIds());
    }

    public function test_auto_sync_off_excludes_user_from_scheduled_sync(): void
    {
        $off = User::factory()->create(['auto_sync' => false]);
        $on = User::factory()->create();
        foreach ([$off, $on] as $u) {
            $post = Post::factory()->upload()->create(['user_id' => $u->id]);
            PostDistribution::create(['post_id' => $post->id, 'platform' => 'youtube', 'status' => 'published', 'external_id' => 'v'.$u->id, 'published_at' => now()->subDay()]);
        }

        $query = app(AnalyticsSync::class)->distributionsQuery(null, false);
        $this->assertSame(2, $query->count()); // query itself is unfiltered
        Http::fake();
        $stats = app(AnalyticsSync::class)->run();
        $this->assertSame(1, $stats['distributions']);
    }

    public function test_delete_me_revokes_and_purges_everything(): void
    {
        Storage::fake('public');
        Http::fake([
            'oauth2.googleapis.com/revoke' => Http::response('', 200),
            'open.tiktokapis.com/v2/oauth/revoke/' => Http::response('', 200),
            'graph.facebook.com/*/permissions*' => Http::response(['success' => true]),
        ]);
        config(['services.meta.graph_version' => 'v26.0']);

        $user = User::factory()->create();
        $other = User::factory()->create();
        SocialAccount::factory()->platform('youtube')->create(['user_id' => $user->id, 'refresh_token' => 'yt-refresh']);
        SocialAccount::factory()->platform('tiktok')->create(['user_id' => $user->id, 'access_token' => 'tt-access']);
        SocialAccount::factory()->platform('facebook')->create(['user_id' => $user->id, 'meta' => ['fb_user_id' => '555']]);
        SocialAccount::factory()->platform('instagram')->create(['user_id' => $user->id, 'meta' => ['fb_user_id' => '555']]);
        $keep = SocialAccount::factory()->platform('youtube')->create(['user_id' => $other->id]);

        Storage::disk('public')->put('videos/a.mp4', 'x');
        $post = Post::factory()->upload()->create(['user_id' => $user->id, 'video_path' => 'videos/a.mp4']);
        $dist = PostDistribution::create(['post_id' => $post->id, 'platform' => 'youtube', 'status' => 'published']);
        PostAnalytic::factory()->forDistribution($dist)->create();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'ExponentPushToken[x]']);
        $user->createToken('mobile');

        Sanctum::actingAs($user);
        $this->deleteJson('/api/me', [])->assertStatus(422)->assertJsonValidationErrors(['confirm']);
        $this->deleteJson('/api/me', ['confirm' => 'delete'])->assertStatus(422);

        $res = $this->deleteJson('/api/me', ['confirm' => 'DELETE'])->assertStatus(202)->assertJsonPath('status', 'scheduled');
        $this->assertTrue(now()->addDays(7)->addMinute()->gte($res->json('completes_by')));

        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2.googleapis.com/revoke') && $r['token'] === 'yt-refresh');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth/revoke') && $r['token'] === 'tt-access');
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/555/permissions'));
        Http::assertSentCount(3); // Meta grant revoked once

        $this->assertNull(User::find($user->id));
        $this->assertSame(0, SocialAccount::where('user_id', $user->id)->count());
        $this->assertSame(0, Post::count());
        $this->assertSame(0, PostAnalytic::count());
        $this->assertSame(0, DeviceToken::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertNotNull($keep->fresh());
        $this->assertSame('completed', DataDeletionRequest::where('source', 'account')->value('status'));
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_me_requires_auth(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->deleteJson('/api/me', ['confirm' => 'DELETE'])->assertUnauthorized();
        $this->getJson('/api/me/preferences')->assertUnauthorized();
    }
}
