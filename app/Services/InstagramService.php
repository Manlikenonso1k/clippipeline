<?php

namespace App\Services;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Publishes Reels through the Instagram Graph API. Reads the IG user id captured
 * at connect time (items 4 & 11), verifies the video URL is publicly reachable,
 * then runs the create-container -> poll-until-FINISHED -> publish flow.
 */
class InstagramService
{
    private const GRAPH = 'https://graph.facebook.com/v19.0';

    /**
     * @throws RuntimeException on any failure (message surfaced to the UI)
     */
    public function publishReel(string $publicVideoUrl, ?string $caption, SocialAccount $account): string
    {
        $igUserId = $account->meta['ig_user_id'] ?? null;
        if (! $igUserId) {
            throw new RuntimeException('Instagram account is missing its business user id. Use Reconnect (or run app:backfill-ig-ids).');
        }

        $token = $account->access_token;
        if (! $token) {
            throw new RuntimeException('Instagram account has no access token; reconnect the account.');
        }

        $this->assertReachable($publicVideoUrl);

        // 1. Create a REELS media container.
        $create = Http::asForm()->post(self::GRAPH."/{$igUserId}/media", [
            'media_type' => 'REELS',
            'video_url' => $publicVideoUrl,
            'caption' => $caption ?? '',
            'access_token' => $token,
        ]);

        if (! $create->successful()) {
            throw new RuntimeException('Instagram container create failed: '.$create->body());
        }

        $creationId = $create->json('id');
        if (! $creationId) {
            throw new RuntimeException('Instagram did not return a creation id.');
        }

        // 2. Poll until the container finishes processing.
        $this->waitUntilFinished($creationId, $token);

        // 3. Publish.
        $publish = Http::asForm()->post(self::GRAPH."/{$igUserId}/media_publish", [
            'creation_id' => $creationId,
            'access_token' => $token,
        ]);

        if (! $publish->successful()) {
            throw new RuntimeException('Instagram publish failed: '.$publish->body());
        }

        $id = $publish->json('id');
        if (! $id) {
            throw new RuntimeException('Instagram publish returned no media id.');
        }

        return $id;
    }

    /**
     * Resolve the IG business user id from an access token, supporting both the
     * Facebook Login flow (/me/accounts -> page -> instagram_business_account) and
     * the Instagram Login flow (/me?fields=user_id). Returns [ig_user_id, flow] or null.
     *
     * @return array{ig_user_id: string, flow: string}|null
     */
    public function resolveBusinessUserId(string $accessToken): ?array
    {
        // Facebook Login: find a Page that has a linked IG business account.
        $pages = Http::get(self::GRAPH.'/me/accounts', [
            'fields' => 'instagram_business_account,name',
            'access_token' => $accessToken,
        ]);

        if ($pages->successful()) {
            foreach ((array) $pages->json('data', []) as $page) {
                $igId = $page['instagram_business_account']['id'] ?? null;
                if ($igId) {
                    return ['ig_user_id' => $igId, 'flow' => 'facebook'];
                }
            }
        }

        // Instagram Login (Instagram API with Instagram Login): /me?fields=user_id.
        $me = Http::get(self::GRAPH.'/me', [
            'fields' => 'user_id,username',
            'access_token' => $accessToken,
        ]);

        if ($me->successful() && $me->json('user_id')) {
            return ['ig_user_id' => (string) $me->json('user_id'), 'flow' => 'instagram'];
        }

        return null;
    }

    private function waitUntilFinished(string $creationId, string $token, int $maxAttempts = 20): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $status = Http::get(self::GRAPH."/{$creationId}", [
                'fields' => 'status_code',
                'access_token' => $token,
            ]);

            $code = $status->json('status_code');

            if ($code === 'FINISHED') {
                return;
            }

            if ($code === 'ERROR' || $code === 'EXPIRED') {
                throw new RuntimeException("Instagram container processing failed (status: {$code}).");
            }

            // IN_PROGRESS / PUBLISHED — wait and retry.
            if ($i < $maxAttempts - 1) {
                sleep(app()->runningUnitTests() ? 0 : 5);
            }
        }

        throw new RuntimeException('Instagram container did not finish processing in time.');
    }

    private function assertReachable(string $url): void
    {
        if (! str_starts_with($url, 'https://')) {
            throw new RuntimeException("Instagram requires a public HTTPS video URL; got: {$url}");
        }

        try {
            $head = Http::head($url);
        } catch (\Throwable $e) {
            throw new RuntimeException('Video URL is not reachable: '.$e->getMessage());
        }

        if (! $head->successful()) {
            throw new RuntimeException("Video URL is not publicly reachable (HTTP {$head->status()}): {$url}");
        }
    }
}
