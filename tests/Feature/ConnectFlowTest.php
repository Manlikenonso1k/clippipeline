<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connect\OAuthState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConnectFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'g-id',
            'services.google.client_secret' => 'g-secret',
            'services.facebook.client_id' => 'fb-id',
            'services.facebook.client_secret' => 'fb-secret',
            'services.facebook.redirect' => 'https://app.test/integrations/callback/instagram',
            'services.tiktok.client_id' => 'tt-key',
            'services.tiktok.client_secret' => 'tt-secret',
            'services.tiktok.redirect' => 'https://app.test/integrations/callback/tiktok',
            'services.meta.graph_version' => 'v26.0',
            'services.mobile.connect_redirect' => 'clippipelinemobile://connect',
        ]);
    }

    private function stateFrom(string $authUrl): string
    {
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);

        return $query['state'];
    }

    public function test_start_returns_auth_url_with_signed_state_round_trip(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/connect/meta/start')->assertOk()
            ->assertJsonPath('provider', 'meta')
            ->assertJsonPath('platform', 'mobile');

        $this->assertStringStartsWith('https://www.facebook.com/v26.0/dialog/oauth?', $res->json('auth_url'));
        $this->assertStringContainsString('instagram_content_publish', urldecode($res->json('auth_url')));
        $this->assertNotNull($res->json('expires_at'));

        $payload = app(OAuthState::class)->decode($this->stateFrom($res->json('auth_url')));
        $this->assertSame($user->id, $payload['uid']);
        $this->assertSame('meta', $payload['provider']);
        $this->assertSame('mobile', $payload['platform']);
        $this->assertNotEmpty($payload['nonce']);

        $yt = $this->getJson('/api/connect/youtube/start?platform=web')->assertOk();
        $url = urldecode($yt->json('auth_url'));
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertStringContainsString('youtube.readonly', $url);
        $this->assertStringContainsString('/integrations/callback/youtube', $url);

        $tt = $this->getJson('/api/connect/tiktok/start')->assertOk();
        $this->assertStringStartsWith('https://www.tiktok.com/v2/auth/authorize/?', $tt->json('auth_url'));
        $this->assertStringContainsString('client_key=tt-key', $tt->json('auth_url'));
    }

    public function test_start_validates_provider_and_platform(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/connect/instagram/start')->assertStatus(422)->assertJsonValidationErrors('provider');
        $this->getJson('/api/connect/youtube/start?platform=desktop')->assertStatus(422)->assertJsonValidationErrors('platform');
    }

    public function test_start_requires_auth(): void
    {
        $this->getJson('/api/connect/youtube/start')->assertUnauthorized();
    }

    public function test_expired_or_tampered_state_is_rejected(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $states = app(OAuthState::class);
        $issued = $states->issue($user->id, 'youtube', 'mobile');

        $this->travel(11)->minutes();
        $this->get('/integrations/callback/youtube?code=abc&state='.urlencode($issued['state']))
            ->assertRedirect()
            ->assertRedirectContains('status=error');
        $this->assertNull($states->decode($issued['state']));
        $this->assertNull($states->decode('garbage'));

        Http::assertNothingSent();
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_state_for_another_provider_is_rejected(): void
    {
        $user = User::factory()->create();
        $state = app(OAuthState::class)->issue($user->id, 'tiktok', 'mobile')['state'];

        $this->get('/integrations/callback/youtube?code=abc&state='.urlencode($state))
            ->assertRedirectContains('clippipelinemobile://connect?provider=youtube&status=error');
    }

    public function test_youtube_callback_mobile_stores_encrypted_tokens_and_deep_links(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.at', 'refresh_token' => '1//rt', 'expires_in' => 3599]),
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [[
                'id' => 'UC123',
                'snippet' => ['title' => 'My Channel', 'customUrl' => '@mychannel', 'thumbnails' => ['high' => ['url' => 'https://img/yt.jpg']]],
                'statistics' => ['subscriberCount' => '1520'],
            ]]]),
        ]);

        $user = User::factory()->create();
        $state = app(OAuthState::class)->issue($user->id, 'youtube', 'mobile')['state'];

        // No session: mobile callback resolves the user from state.
        $this->get('/integrations/callback/youtube?code=c0de&state='.urlencode($state))
            ->assertRedirect('clippipelinemobile://connect?provider=youtube&status=ok');

        $account = SocialAccount::where('user_id', $user->id)->where('provider', 'youtube')->firstOrFail();
        $this->assertSame('ya29.at', $account->access_token);
        $this->assertSame('1//rt', $account->refresh_token);
        $this->assertSame('UC123', $account->external_id);
        $this->assertSame('@mychannel', $account->handle);
        $this->assertSame(1520, $account->follower_count);
        $this->assertSame('active', $account->status);

        $raw = DB::table('social_accounts')->where('id', $account->id)->first();
        $this->assertNotSame('ya29.at', $raw->access_token);
        $this->assertSame('ya29.at', Crypt::decryptString($raw->access_token));
        $this->assertSame('1//rt', Crypt::decryptString($raw->refresh_token));

        Http::assertSent(fn ($r) => $r->url() === 'https://oauth2.googleapis.com/token'
            && $r['grant_type'] === 'authorization_code'
            && str_ends_with($r['redirect_uri'], '/integrations/callback/youtube'));
    }

    public function test_meta_callback_creates_facebook_and_instagram_rows(): void
    {
        Http::fake([
            'graph.facebook.com/v26.0/oauth/access_token*' => Http::sequence()
                ->push(['access_token' => 'short-user', 'token_type' => 'bearer'])
                ->push(['access_token' => 'long-user', 'token_type' => 'bearer', 'expires_in' => 5183944]),
            'graph.facebook.com/v26.0/me/accounts*' => Http::response(['data' => [
                ['id' => 'PAGE_NO_IG', 'name' => 'Other Page', 'access_token' => 'page-token-0'],
                [
                    'id' => 'PAGE1', 'name' => 'ClipPipeline', 'access_token' => 'page-token-1',
                    'picture' => ['data' => ['url' => 'https://img/page.jpg']], 'followers_count' => 300,
                    'instagram_business_account' => [
                        'id' => '17841400000000000', 'username' => 'clippipeline', 'name' => 'ClipPipeline IG',
                        'profile_picture_url' => 'https://img/ig.jpg', 'followers_count' => 1520,
                    ],
                ],
            ]]),
        ]);

        $user = User::factory()->create();
        $state = app(OAuthState::class)->issue($user->id, 'meta', 'mobile')['state'];

        $this->get('/integrations/callback/instagram?code=c0de&state='.urlencode($state))
            ->assertRedirect('clippipelinemobile://connect?provider=meta&status=ok');

        $fb = SocialAccount::where('user_id', $user->id)->where('provider', 'facebook')->firstOrFail();
        $ig = SocialAccount::where('user_id', $user->id)->where('provider', 'instagram')->firstOrFail();

        $this->assertSame('PAGE1', $fb->external_id);
        $this->assertSame('page-token-1', $fb->access_token);
        $this->assertSame(300, $fb->follower_count);
        $this->assertSame('17841400000000000', $ig->external_id);
        $this->assertSame('17841400000000000', $ig->meta['ig_user_id']);
        $this->assertSame('PAGE1', $ig->meta['page_id']);
        $this->assertSame('page-token-1', $ig->access_token);
        $this->assertSame('@clippipeline', $ig->handle);
        $this->assertSame('active', $ig->status);
        $this->assertNull($ig->expires_at);

        $raw = DB::table('social_accounts')->where('id', $ig->id)->value('access_token');
        $this->assertNotSame('page-token-1', $raw);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'fb_exchange_token=short-user'));
    }

    public function test_meta_without_instagram_business_account_stores_error_row(): void
    {
        Http::fake([
            'graph.facebook.com/v26.0/oauth/access_token*' => Http::response(['access_token' => 'tok', 'expires_in' => 5183944]),
            'graph.facebook.com/v26.0/me/accounts*' => Http::response(['data' => [
                ['id' => 'PAGE1', 'name' => 'Solo Page', 'access_token' => 'page-token'],
            ]]),
        ]);

        $user = User::factory()->create();
        $state = app(OAuthState::class)->issue($user->id, 'meta', 'web')['state'];

        $this->get('/integrations/callback/instagram?code=c&state='.urlencode($state))
            ->assertRedirect(route('filament.admin.pages.connect-accounts'));

        $ig = SocialAccount::where('user_id', $user->id)->where('provider', 'instagram')->firstOrFail();
        $this->assertSame('error', $ig->status);
        $this->assertStringContainsString('Instagram', $ig->last_error);
        $this->assertSame('active', SocialAccount::where('user_id', $user->id)->where('provider', 'facebook')->value('status'));
    }

    public function test_tiktok_callback_web_redirects_to_filament(): void
    {
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response([
                'access_token' => 'act.tt', 'refresh_token' => 'rft.tt', 'expires_in' => 86400,
                'refresh_expires_in' => 31536000, 'open_id' => 'open-1', 'scope' => 'user.info.basic,video.upload',
            ]),
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => [
                'open_id' => 'open-1', 'display_name' => 'Tok Star', 'avatar_url' => 'https://img/tt.jpg',
            ]], 'error' => ['code' => 'ok']]),
        ]);

        $user = User::factory()->create();
        $state = app(OAuthState::class)->issue($user->id, 'tiktok', 'web')['state'];

        $this->get('/integrations/callback/tiktok?code=c&state='.urlencode($state))
            ->assertRedirect(route('filament.admin.pages.connect-accounts'))
            ->assertSessionHas('status');

        $tt = SocialAccount::where('user_id', $user->id)->where('provider', 'tiktok')->firstOrFail();
        $this->assertSame('act.tt', $tt->access_token);
        $this->assertSame('rft.tt', $tt->refresh_token);
        $this->assertSame('open-1', $tt->external_id);
        $this->assertSame('Tok Star', $tt->display_name);
        $this->assertNotSame('act.tt', DB::table('social_accounts')->where('id', $tt->id)->value('access_token'));

        Http::assertSent(fn ($r) => str_contains($r->url(), '/v2/oauth/token/') && $r['client_key'] === 'tt-key'
            && $r['redirect_uri'] === 'https://app.test/integrations/callback/tiktok');
    }

    public function test_provider_error_redirects_mobile_with_message(): void
    {
        $user = User::factory()->create();
        $state = app(OAuthState::class)->issue($user->id, 'tiktok', 'mobile')['state'];

        $res = $this->get('/integrations/callback/tiktok?error=access_denied&error_description=User+cancelled&state='.urlencode($state));
        $res->assertRedirectContains('clippipelinemobile://connect?provider=tiktok&status=error&message=');
        $this->assertStringContainsString('User%20cancelled', $res->headers->get('Location'));
    }

    public function test_legacy_callback_without_state_uses_session_user(): void
    {
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 86400, 'open_id' => 'o']),
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['open_id' => 'o']]]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->get('/integrations/callback/tiktok?code=c')
            ->assertRedirect(route('filament.admin.pages.connect-accounts'));
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'tiktok']);
    }

    public function test_web_redirect_uses_same_service_and_accepts_instagram_alias(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->get('/integrations/redirect/instagram');
        $res->assertRedirect();
        $location = $res->headers->get('Location');
        $this->assertStringStartsWith('https://www.facebook.com/v26.0/dialog/oauth?', $location);

        $payload = app(OAuthState::class)->decode($this->stateFrom($location));
        $this->assertSame('web', $payload['platform']);
        $this->assertSame('meta', $payload['provider']);
    }
}
