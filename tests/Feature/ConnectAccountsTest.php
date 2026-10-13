<?php

namespace Tests\Feature;

use App\Exceptions\NeedsReauthException;
use App\Models\DeviceToken;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connect\TokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConnectAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_own_accounts_in_contract_shape_and_order(): void
    {
        $user = User::factory()->create();
        SocialAccount::factory()->for($user)->platform('tiktok')->create();
        SocialAccount::factory()->for($user)->platform('instagram')->create(['meta' => ['ig_user_id' => '178']]);
        SocialAccount::factory()->for($user)->platform('youtube')->needsReauth()->create();
        SocialAccount::factory()->platform('youtube')->create(); // someone else's

        Sanctum::actingAs($user);

        $res = $this->getJson('/api/social-accounts')->assertOk()->assertJsonCount(3);
        $this->assertSame(['youtube', 'instagram', 'tiktok'], array_column($res->json(), 'platform'));

        $res->assertJsonStructure([['id', 'platform', 'provider', 'connect_provider', 'handle', 'display_name', 'avatar_url',
            'external_id', 'follower_count', 'status', 'connected', 'expires_at', 'last_synced_at', 'error']]);
        $res->assertJsonPath('0.status', 'needs_reauth')
            ->assertJsonPath('0.connected', false)
            ->assertJsonPath('0.error', 'Token revoked.')
            ->assertJsonPath('1.connect_provider', 'meta')
            ->assertJsonPath('1.connected', true)
            ->assertJsonPath('1.error', null);
        $this->assertArrayNotHasKey('access_token', $res->json(0));
    }

    public function test_delete_meta_row_deletes_both_meta_rows_scoped_to_user(): void
    {
        $user = User::factory()->create();
        $fb = SocialAccount::factory()->for($user)->platform('facebook')->create();
        $ig = SocialAccount::factory()->for($user)->platform('instagram')->create();
        $yt = SocialAccount::factory()->for($user)->platform('youtube')->create();
        $otherIg = SocialAccount::factory()->platform('instagram')->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/social-accounts/{$otherIg->id}")->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        $this->assertDatabaseHas('social_accounts', ['id' => $otherIg->id]);

        $this->deleteJson("/api/social-accounts/{$ig->id}")->assertOk()->assertExactJson(['deleted_ids' => [$fb->id, $ig->id]]);
        $this->assertDatabaseMissing('social_accounts', ['id' => $fb->id]);
        $this->assertDatabaseHas('social_accounts', ['id' => $yt->id]);
        $this->assertDatabaseHas('social_accounts', ['id' => $otherIg->id]);

        $this->deleteJson("/api/social-accounts/{$yt->id}")->assertOk()->assertExactJson(['deleted_ids' => [$yt->id]]);
        $this->deleteJson('/api/social-accounts/999999')->assertNotFound();
    }

    public function test_fresh_token_refreshes_expiring_google_token(): void
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-at', 'expires_in' => 3600])]);

        $account = SocialAccount::factory()->platform('youtube')->expired()->create(['refresh_token' => 'rt']);

        $this->assertSame('new-at', app(TokenManager::class)->freshAccessToken($account));
        $account->refresh();
        $this->assertSame('new-at', $account->access_token);
        $this->assertSame('rt', $account->refresh_token);
        $this->assertTrue($account->expires_at->isFuture());
    }

    public function test_valid_token_is_returned_without_refresh(): void
    {
        Http::fake();
        $account = SocialAccount::factory()->platform('tiktok')->create(['access_token' => 'still-good']);

        $this->assertSame('still-good', app(TokenManager::class)->freshAccessToken($account));
        Http::assertNothingSent();
    }

    public function test_refresh_failure_marks_needs_reauth_and_pushes(): void
    {
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Refresh token is invalid'], 400),
            'exp.host/*' => Http::response(['data' => []]),
        ]);

        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'ExponentPushToken[abc]', 'platform' => 'android']);
        $account = SocialAccount::factory()->for($user)->platform('tiktok')->expired()->create();

        try {
            app(TokenManager::class)->freshAccessToken($account);
            $this->fail('Expected NeedsReauthException');
        } catch (NeedsReauthException) {
        }

        $account->refresh();
        $this->assertSame('needs_reauth', $account->status);
        $this->assertStringContainsString('Refresh token is invalid', $account->last_error);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'exp.host')
            && $r[0]['data'] === ['type' => 'account_needs_reauth', 'screen' => 'connect', 'provider' => 'tiktok']);

        // Already flagged: fails fast without calling TikTok again.
        $this->expectException(NeedsReauthException::class);
        app(TokenManager::class)->freshAccessToken($account);
    }

    public function test_invalid_meta_page_token_flags_both_rows(): void
    {
        Http::fake([
            'graph.facebook.com/*/debug_token*' => Http::response(['data' => ['is_valid' => false, 'error' => ['message' => 'Session has been invalidated']]]),
            'exp.host/*' => Http::response(['data' => []]),
        ]);

        $user = User::factory()->create();
        $fb = SocialAccount::factory()->for($user)->platform('facebook')->create(['expires_at' => null]);
        $ig = SocialAccount::factory()->for($user)->platform('instagram')->create(['expires_at' => null]);

        try {
            app(TokenManager::class)->freshAccessToken($ig);
            $this->fail('Expected NeedsReauthException');
        } catch (NeedsReauthException) {
        }

        $this->assertSame('needs_reauth', $fb->fresh()->status);
        $this->assertSame('needs_reauth', $ig->fresh()->status);
    }

    public function test_refresh_tokens_command_refreshes_expiring_accounts(): void
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'cmd-at', 'expires_in' => 3600])]);

        $soon = SocialAccount::factory()->platform('youtube')->create(['expires_at' => now()->addMinutes(30)]);
        $later = SocialAccount::factory()->platform('youtube')->create(['expires_at' => now()->addDays(5), 'access_token' => 'keep']);

        $this->artisan('accounts:refresh-tokens')->assertSuccessful();

        $this->assertSame('cmd-at', $soon->fresh()->access_token);
        $this->assertSame('keep', $later->fresh()->access_token);
    }

    public function test_encrypt_migration_encrypts_plaintext_and_skips_encrypted(): void
    {
        $user = User::factory()->create();
        DB::table('social_accounts')->insert([
            ['user_id' => $user->id, 'provider' => 'youtube', 'platform_name' => 'youtube', 'access_token' => 'plain-at', 'refresh_token' => 'plain-rt', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $user->id, 'provider' => 'tiktok', 'platform_name' => 'tiktok', 'access_token' => Crypt::encryptString('enc-at'), 'refresh_token' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migration = require database_path('migrations/2026_10_13_100200_encrypt_social_account_tokens.php');
        $migration->up();
        $migration->up(); // idempotent

        $yt = SocialAccount::where('provider', 'youtube')->first();
        $tt = SocialAccount::where('provider', 'tiktok')->first();
        $this->assertSame('plain-at', $yt->access_token);
        $this->assertSame('plain-rt', $yt->refresh_token);
        $this->assertSame('enc-at', $tt->access_token);
        $this->assertNull($tt->refresh_token);
    }
}
