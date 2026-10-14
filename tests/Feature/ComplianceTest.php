<?php

namespace Tests\Feature;

use App\Models\AccountFollowerSnapshot;
use App\Models\DataDeletionRequest;
use App\Models\Post;
use App\Models\PostAnalytic;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Compliance\MetaSignedRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** CONTRACT §15, COMPLIANCE C-01..C-08, A-01, A-03, A-04. */
class ComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.facebook.client_id' => 'fid', 'services.facebook.client_secret' => 'meta-secret',
            'services.tiktok.client_id' => 'tkey', 'services.tiktok.client_secret' => 'tiktok-secret',
            'services.meta.graph_version' => 'v26.0',
        ]);
    }

    private function metaUser(): User
    {
        $user = User::factory()->create();
        foreach (['facebook', 'instagram'] as $p) {
            SocialAccount::factory()->platform($p)->create(['user_id' => $user->id, 'meta' => ['fb_user_id' => '1234']]);
        }
        SocialAccount::factory()->platform('youtube')->create(['user_id' => $user->id]);
        $post = Post::factory()->upload()->create(['user_id' => $user->id]);
        $fb = PostDistribution::create(['post_id' => $post->id, 'platform' => 'facebook', 'status' => 'published']);
        PostDistribution::create(['post_id' => $post->id, 'platform' => 'instagram', 'status' => 'queued']);
        $yt = PostDistribution::create(['post_id' => $post->id, 'platform' => 'youtube', 'status' => 'published']);
        PostAnalytic::factory()->forDistribution($fb)->create();
        PostAnalytic::factory()->forDistribution($yt)->create();

        return $user;
    }

    public function test_meta_signed_request_parser(): void
    {
        $signed = MetaSignedRequest::make(['user_id' => '1234'], 'meta-secret');
        $this->assertSame('1234', MetaSignedRequest::parse($signed)['user_id']);
        $this->assertNull(MetaSignedRequest::parse(MetaSignedRequest::make(['user_id' => '1234'], 'wrong')));
        $this->assertNull(MetaSignedRequest::parse('garbage'));
        [$sig] = explode('.', $signed);
        $this->assertNull(MetaSignedRequest::parse($sig.'.'.rtrim(strtr(base64_encode('{"user_id":"9","algorithm":"HMAC-SHA256"}'), '+/', '-_'), '=')));
    }

    public function test_meta_data_deletion_callback_purges_and_reports_status(): void
    {
        $user = $this->metaUser();

        $this->post('/api/meta/data-deletion', ['signed_request' => MetaSignedRequest::make(['user_id' => '1234'], 'wrong')])->assertStatus(400);
        $this->post('/api/meta/data-deletion', [])->assertStatus(400);
        $this->assertSame(2, SocialAccount::whereIn('provider', ['facebook', 'instagram'])->count());

        $res = $this->post('/api/meta/data-deletion', ['signed_request' => MetaSignedRequest::make(['user_id' => '1234'], 'meta-secret')])
            ->assertOk()->assertJsonStructure(['url', 'confirmation_code']);
        $code = $res->json('confirmation_code');
        $this->assertSame(url('/data-deletion/status').'?code='.$code, $res->json('url'));

        $this->assertSame(['youtube'], SocialAccount::where('user_id', $user->id)->pluck('provider')->all());
        $this->assertSame(['youtube'], PostDistribution::whereIn('id', PostAnalytic::pluck('distribution_id'))->pluck('platform')->all());
        $this->assertSame('failed', PostDistribution::where('platform', 'instagram')->value('status'));
        $request = DataDeletionRequest::where('code', $code)->firstOrFail();
        $this->assertSame('completed', $request->status);
        $this->assertNotSame('1234', $request->subject_hash); // no raw ids stored

        $this->get('/data-deletion/status?code='.$code)->assertOk()->assertSee($code)->assertSee('completed');
        $this->get('/data-deletion/status?code=NOPE')->assertOk()->assertSee('could not find');
    }

    public function test_meta_deauthorize_removes_meta_rows(): void
    {
        $user = $this->metaUser();
        $this->post('/api/meta/deauthorize', ['signed_request' => 'x.y'])->assertStatus(400);
        $this->post('/api/meta/deauthorize', ['signed_request' => MetaSignedRequest::make(['user_id' => '1234'], 'meta-secret')])
            ->assertOk()->assertExactJson(['ok' => true]);

        $this->assertSame(['youtube'], SocialAccount::where('user_id', $user->id)->pluck('provider')->all());
        $this->assertSame('failed', PostDistribution::where('platform', 'instagram')->value('status'));
    }

    private function tiktokEvent(array $event, ?string $secret = 'tiktok-secret', ?int $t = null)
    {
        $body = json_encode($event);
        $t ??= time();
        $sig = hash_hmac('sha256', $t.'.'.$body, (string) $secret);

        return $this->call('POST', '/api/webhooks/tiktok', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_TIKTOK_SIGNATURE' => "t={$t},s={$sig}",
        ], $body);
    }

    public function test_tiktok_webhook_signature_and_authorization_removed(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $account = SocialAccount::factory()->platform('tiktok')->create(['user_id' => $user->id, 'external_id' => 'open-1']);
        $post = Post::factory()->upload()->create(['user_id' => $user->id]);
        PostDistribution::create(['post_id' => $post->id, 'platform' => 'tiktok', 'status' => 'queued']);
        $event = ['client_key' => 'tkey', 'event' => 'authorization.removed', 'create_time' => time(), 'user_openid' => 'open-1', 'content' => json_encode(['reason' => 1])];

        $this->tiktokEvent($event, 'wrong')->assertStatus(401);
        $this->tiktokEvent($event, 'tiktok-secret', time() - 3600)->assertStatus(401); // stale
        $this->postJson('/api/webhooks/tiktok', $event)->assertStatus(401);
        $this->assertSame('active', $account->fresh()->status);

        $this->tiktokEvent($event)->assertOk()->assertExactJson(['ok' => true]);
        $this->tiktokEvent($event)->assertOk(); // idempotent

        $account->refresh();
        $this->assertSame('needs_reauth', $account->status);
        $this->assertNull($account->access_token);
        $this->assertNull($account->refresh_token);
        $this->assertNotNull($account->reauth_required_at);
        $this->assertSame('failed', PostDistribution::value('status'));
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_tiktok_webhook_post_publish_events(): void
    {
        $user = User::factory()->create();
        SocialAccount::factory()->platform('tiktok')->create(['user_id' => $user->id, 'handle' => '@nonso']);
        $post = Post::factory()->upload()->create(['user_id' => $user->id]);
        $a = PostDistribution::create(['post_id' => $post->id, 'platform' => 'tiktok', 'status' => 'processing', 'meta' => ['tiktok_publish_id' => 'pub-1']]);
        $post2 = Post::factory()->upload()->create(['user_id' => $user->id]);
        $b = PostDistribution::create(['post_id' => $post2->id, 'platform' => 'tiktok', 'status' => 'processing', 'meta' => ['tiktok_publish_id' => 'pub-2']]);

        $this->tiktokEvent(['event' => 'post.publish.failed', 'user_openid' => 'x', 'content' => json_encode(['publish_id' => 'pub-1', 'reason' => 'duration_check', 'publish_type' => 'DIRECT_PUBLISH'])])->assertOk();
        $this->assertSame('failed', $a->fresh()->status);
        $this->assertStringContainsString('duration_check', $a->fresh()->error_message);

        $this->tiktokEvent(['event' => 'post.publish.complete', 'user_openid' => 'x', 'content' => json_encode(['publish_id' => 'pub-2'])])->assertOk();
        $this->assertSame('published', $b->fresh()->status);

        $this->tiktokEvent(['event' => 'post.publish.publicly_available', 'user_openid' => 'x', 'content' => json_encode(['publish_id' => 'pub-2', 'post_id' => '777'])])->assertOk();
        $b->refresh();
        $this->assertSame('777', $b->external_id);
        $this->assertSame('https://www.tiktok.com/@nonso/video/777', $b->externalUrl());
    }

    public function test_disconnect_revokes_at_each_platform(): void
    {
        Http::fake([
            'oauth2.googleapis.com/revoke' => Http::response('', 200),
            'open.tiktokapis.com/v2/oauth/revoke/' => Http::response('', 200),
            'graph.facebook.com/v26.0/debug_token*' => Http::response(['data' => ['user_id' => '999']]),
            'graph.facebook.com/v26.0/999/permissions*' => Http::response(['success' => true]),
        ]);
        $user = User::factory()->create();
        $yt = SocialAccount::factory()->platform('youtube')->create(['user_id' => $user->id, 'refresh_token' => 'R']);
        $tt = SocialAccount::factory()->platform('tiktok')->create(['user_id' => $user->id, 'access_token' => 'A']);
        $fb = SocialAccount::factory()->platform('facebook')->create(['user_id' => $user->id]); // legacy row: no fb_user_id
        SocialAccount::factory()->platform('instagram')->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/social-accounts/{$yt->id}")->assertOk()->assertJsonPath('deleted_ids', [$yt->id]);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2.googleapis.com/revoke') && $r['token'] === 'R');

        $this->deleteJson("/api/social-accounts/{$tt->id}")->assertOk();
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth/revoke') && $r['token'] === 'A' && $r['client_key'] === 'tkey');

        $this->deleteJson("/api/social-accounts/{$fb->id}")->assertOk()->assertJsonCount(2, 'deleted_ids');
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/999/permissions'));
        $this->assertSame(0, SocialAccount::count());

        // Revoke failure never blocks the delete.
        Http::fake(['*' => Http::response('', 500)]);
        $again = SocialAccount::factory()->platform('youtube')->create(['user_id' => $user->id]);
        $this->deleteJson("/api/social-accounts/{$again->id}")->assertOk();
    }

    public function test_retention_purges_unauthorized_and_prunes_old_snapshots(): void
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'new', 'expires_in' => 3600])]);
        $user = User::factory()->create();
        $stale = SocialAccount::factory()->platform('tiktok')->needsReauth()->create(['user_id' => $user->id, 'reauth_required_at' => now()->subDays(31)]);
        $recent = SocialAccount::factory()->platform('instagram')->needsReauth()->create(['user_id' => $user->id, 'reauth_required_at' => now()->subDays(5)]);
        $active = SocialAccount::factory()->platform('youtube')->create(['user_id' => $user->id, 'refresh_token' => 'R']);

        $post = Post::factory()->upload()->create(['user_id' => $user->id]);
        $tt = PostDistribution::create(['post_id' => $post->id, 'platform' => 'tiktok', 'status' => 'published']);
        $yt = PostDistribution::create(['post_id' => $post->id, 'platform' => 'youtube', 'status' => 'published']);
        PostAnalytic::factory()->forDistribution($tt)->create();
        PostAnalytic::factory()->forDistribution($yt, now()->subMonths(37)->toDateString())->create();
        $keep = PostAnalytic::factory()->forDistribution($yt)->create();
        AccountFollowerSnapshot::create(['social_account_id' => $active->id, 'platform' => 'youtube', 'followers' => 1, 'captured_on' => now()->subMonths(37)->toDateString()]);

        $this->artisan('accounts:retention')->assertSuccessful();

        $this->assertNull($stale->fresh());
        $this->assertNotNull($recent->fresh());
        $this->assertSame([$keep->id], PostAnalytic::pluck('id')->all());
        $this->assertSame(0, AccountFollowerSnapshot::count());
        $this->assertNotNull($active->fresh()->last_authorized_check_at);
        $this->assertSame('new', $active->fresh()->access_token);
    }

    public function test_public_pages_render_with_required_content(): void
    {
        $this->get('/privacy')->assertOk()
            ->assertSee('Limited Use requirements')
            ->assertSee('https://www.youtube.com/t/terms', false)
            ->assertSee('https://policies.google.com/privacy', false)
            ->assertSee('https://myaccount.google.com/permissions', false)
            ->assertSee('Manage app permissions')
            ->assertSee('Last updated');
        $this->get('/terms')->assertOk()
            ->assertSee('you agree to be bound by the')
            ->assertSee('Music Usage Confirmation')
            ->assertSee('Branded Content Policy')
            ->assertSee('Meta Platform Terms')
            ->assertSee('not affiliated');
        $this->get('/data-deletion')->assertOk()->assertSee('Disconnect');
        $this->get('/data-deletion/status')->assertOk();
        $this->get('/delete-account')->assertOk()->assertSee('ClipPipeline')->assertSee('Sign in with Google to delete')->assertSee(config('clippipeline.support_email'));

        foreach (['/', '/privacy', '/terms', '/data-deletion', '/delete-account'] as $page) {
            $this->get($page)->assertOk()
                ->assertSee('href="'.url('/data-deletion').'"', false)
                ->assertSee('href="'.url('/delete-account').'"', false)
                ->assertSee('mailto:'.config('clippipeline.support_email'), false)
                ->assertSee('is not affiliated with');
        }
    }

    public function test_web_delete_account_flow(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/delete-account')->assertSee('Type');
        $this->actingAs($user)->post('/delete-account', ['confirm' => 'nope'])->assertSessionHasErrors('confirm');
        $this->actingAs($user)->post('/delete-account', ['confirm' => 'DELETE'])->assertRedirect('/delete-account');
        $this->assertNull(User::find($user->id));
    }
}
