<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Auth\LoginBroker;
use App\Services\Connect\OAuthState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** CONTRACT §9 mobile sign-in broker. */
class AuthBrokerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google.client_id' => 'gid', 'services.google.client_secret' => 'gsecret',
            'services.google.redirect' => 'https://clip.test/auth/callback/google',
            'services.facebook.client_id' => 'fid', 'services.facebook.client_secret' => 'fsecret',
            'services.facebook.redirect' => 'https://clip.test/integrations/callback/instagram',
            'services.tiktok.client_id' => 'tkey', 'services.tiktok.client_secret' => 'tsecret',
            'services.tiktok.redirect' => 'https://clip.test/integrations/callback/tiktok',
            'services.meta.graph_version' => 'v26.0',
        ]);
        Http::preventStrayRequests();
    }

    private function stateFrom(string $authUrl): string
    {
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $q);

        return $q['state'];
    }

    private function codeFrom(string $location): string
    {
        $this->assertStringStartsWith('clippipelinemobile://auth?status=ok&code=', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $q);

        return $q['code'];
    }

    public function test_start_returns_auth_urls_with_login_state_on_existing_redirect_uris(): void
    {
        foreach ([
            'google' => ['accounts.google.com', 'https://clip.test/auth/callback/google'],
            'facebook' => ['www.facebook.com/v26.0/dialog/oauth', 'https://clip.test/integrations/callback/instagram'],
            'tiktok' => ['www.tiktok.com/v2/auth/authorize', 'https://clip.test/integrations/callback/tiktok'],
        ] as $provider => [$host, $redirect]) {
            $res = $this->getJson("/api/auth/{$provider}/start?platform=mobile")->assertOk()
                ->assertJsonPath('provider', $provider)
                ->assertJsonStructure(['provider', 'auth_url', 'expires_at']);

            $url = $res->json('auth_url');
            $this->assertStringContainsString($host, $url);
            $this->assertStringContainsString(urlencode($redirect), $url);

            $state = app(OAuthState::class)->decodeLogin($this->stateFrom($url));
            $this->assertSame(['login', $provider, 'mobile'], [$state['purpose'], $state['provider'], $state['platform']]);
        }

        $this->getJson('/api/auth/myspace/start')->assertNotFound();
    }

    public function test_google_broker_issues_single_use_code(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'g-at']),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response(['sub' => 'g-1', 'email' => 'a@example.com', 'name' => 'Ada', 'picture' => 'https://p/a.png']),
        ]);

        $state = $this->stateFrom($this->getJson('/api/auth/google/start')->json('auth_url'));
        $location = $this->get('/auth/callback/google?'.http_build_query(['code' => 'c1', 'state' => $state]))->assertRedirect()->headers->get('Location');
        $code = $this->codeFrom($location);

        $user = User::where('google_id', 'g-1')->firstOrFail();
        $this->assertSame('a@example.com', $user->email);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2.googleapis.com/token') && $r['redirect_uri'] === 'https://clip.test/auth/callback/google');

        $this->postJson('/api/auth/exchange', ['code' => $code, 'device_name' => 'android'])->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'a@example.com')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'avatar']]);
        $this->assertSame(1, $user->tokens()->count());

        // Single use.
        $this->postJson('/api/auth/exchange', ['code' => $code])->assertStatus(422)->assertJsonValidationErrors(['code']);
        $this->postJson('/api/auth/exchange', ['code' => 'nope'])->assertStatus(422);
    }

    public function test_code_expires_after_five_minutes(): void
    {
        $code = app(LoginBroker::class)->issueCode(User::factory()->create());
        $this->travel(301)->seconds();
        $this->postJson('/api/auth/exchange', ['code' => $code])->assertStatus(422);
    }

    public function test_facebook_broker_uses_integration_callback_and_links_by_email(): void
    {
        $existing = User::factory()->create(['email' => 'fb@example.com']);
        Http::fake([
            'graph.facebook.com/v26.0/oauth/access_token*' => Http::response(['access_token' => 'fb-at']),
            'graph.facebook.com/v26.0/me*' => Http::response(['id' => 'fb-9', 'name' => 'Fe', 'email' => 'fb@example.com', 'picture' => ['data' => ['url' => 'https://p/f.png']]]),
        ]);

        $state = $this->stateFrom($this->getJson('/api/auth/facebook/start')->json('auth_url'));
        $location = $this->get('/integrations/callback/instagram?'.http_build_query(['code' => 'c', 'state' => $state]))->headers->get('Location');
        $this->codeFrom($location);

        $this->assertSame('fb-9', $existing->fresh()->facebook_id);
        $this->assertSame(0, SocialAccount::count()); // login only, nothing connected
    }

    public function test_tiktok_broker_creates_user_keyed_on_open_id(): void
    {
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response(['access_token' => 'tt-at', 'open_id' => 'open-1']),
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['open_id' => 'open-1', 'display_name' => 'Tee']]]),
        ]);

        $state = $this->stateFrom($this->getJson('/api/auth/tiktok/start')->json('auth_url'));
        $location = $this->get('/integrations/callback/tiktok?'.http_build_query(['code' => 'c', 'state' => $state]))->headers->get('Location');
        $code = $this->codeFrom($location);

        $this->assertSame('Tee', User::where('tiktok_id', 'open-1')->value('name'));
        $this->postJson('/api/auth/exchange', ['code' => $code])->assertOk();
    }

    public function test_failures_redirect_with_error(): void
    {
        $state = $this->stateFrom($this->getJson('/api/auth/tiktok/start')->json('auth_url'));
        $location = $this->get('/integrations/callback/tiktok?'.http_build_query(['error' => 'access_denied', 'state' => $state]))->headers->get('Location');
        $this->assertStringStartsWith('clippipelinemobile://auth?status=error&message=', $location);

        // A google state replayed on the tiktok callback is rejected.
        $google = $this->stateFrom($this->getJson('/api/auth/google/start')->json('auth_url'));
        $location = $this->get('/integrations/callback/tiktok?'.http_build_query(['code' => 'c', 'state' => $google]))->headers->get('Location');
        $this->assertStringStartsWith('clippipelinemobile://auth?status=error', $location);

        // Expired state.
        $this->travel(11)->minutes();
        $location = $this->get('/auth/callback/google?'.http_build_query(['code' => 'c', 'state' => $google]))->headers->get('Location');
        $this->assertStringStartsWith('clippipelinemobile://auth?status=error', $location);
        $this->assertSame(0, User::count());
    }
}
