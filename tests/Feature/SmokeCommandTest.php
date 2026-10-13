<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmokeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prints_a_status_line_per_connected_account(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-at', 'expires_in' => 3600]),
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['statistics' => ['subscriberCount' => '42']]]]),
            'open.tiktokapis.com/v2/oauth/token/' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Refresh token is invalid'], 400),
            'exp.host/*' => Http::response(['data' => []]),
        ]);

        $user = User::factory()->create(['email' => 'smoke@example.com']);
        SocialAccount::factory()->for($user)->platform('youtube')->create(['refresh_token' => 'rt', 'handle' => '@chan']);
        SocialAccount::factory()->for($user)->platform('tiktok')->create(['refresh_token' => 'rt']);
        SocialAccount::factory()->for($user)->platform('instagram')->create(['status' => 'error', 'last_error' => 'No Instagram business account linked.']);

        $this->artisan('app:smoke', ['email' => 'smoke@example.com'])
            ->expectsOutputToContain('youtube    OK            @chan · followers=42')
            ->expectsOutputToContain('instagram  ERROR         No Instagram business account linked.')
            ->expectsOutputToContain('tiktok     NEEDS_REAUTH')
            ->assertFailed();

        $this->assertSame('new-at', SocialAccount::where('provider', 'youtube')->first()->access_token);
        $this->assertDatabaseCount('post_analytics', 0);
        $this->assertDatabaseCount('account_follower_snapshots', 0);
    }

    public function test_unknown_user_fails(): void
    {
        $this->artisan('app:smoke', ['email' => 'nobody@example.com'])->assertFailed();
    }
}
