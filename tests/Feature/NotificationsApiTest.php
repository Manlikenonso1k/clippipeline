<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\ExpoPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** CONTRACT §11. */
class NotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_stores_notification_and_feed_lists_newest_first(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'ExponentPushToken[a]']);
        $push = app(ExpoPushService::class);

        $push->sendToUser($user, 'Post failed', 'Tap to review', ['type' => 'distribution_failed', 'screen' => 'post', 'postId' => 7]);
        $this->travel(1)->minutes();
        $push->sendToUser($user, 'Reconnect TikTok', 'Lost access', ['type' => 'account_needs_reauth', 'screen' => 'connect', 'provider' => 'tiktok']);
        Http::assertSentCount(2);

        $other = User::factory()->create();
        $push->sendToUser($other, 'Other', 'x', ['type' => 'distribution_published', 'postId' => 1]);

        Sanctum::actingAs($user);
        $this->getJson('/api/notifications?limit=30')->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.type', 'account_needs_reauth')
            ->assertJsonPath('items.0.data.provider', 'tiktok')
            ->assertJsonPath('items.1.title', 'Post failed')
            ->assertJsonPath('items.1.data.postId', 7)
            ->assertJsonPath('items.1.read_at', null)
            ->assertJsonStructure(['items' => [['id', 'type', 'title', 'body', 'data', 'read_at', 'created_at']]]);

        $this->postJson('/api/notifications/read-all')->assertNoContent();
        $this->getJson('/api/notifications')->assertJsonPath('unread_count', 0);
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_notifications_disabled_stores_but_does_not_push(): void
    {
        Http::fake();
        $user = User::factory()->create(['notifications_enabled' => false]);
        DeviceToken::create(['user_id' => $user->id, 'token' => 'ExponentPushToken[a]']);

        app(ExpoPushService::class)->sendToUser($user, 'Clip published', 'Live', ['type' => 'distribution_published', 'postId' => 3]);

        Http::assertNothingSent();
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }
}
