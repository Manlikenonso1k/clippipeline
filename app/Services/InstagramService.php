<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Services\Connect\TokenManager;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Instagram Reels via the Instagram Graph API (Facebook Login flow, Page token):
 * POST /{ig-user-id}/media (REELS) -> GET /{container}?fields=status_code until
 * FINISHED -> POST /{ig-user-id}/media_publish. Meta fetches the video by URL.
 */
class InstagramService
{
    public function graph(): string
    {
        return 'https://graph.facebook.com/'.config('services.meta.graph_version', 'v24.0');
    }

    /** IG user id: social_accounts.external_id, falling back to meta['ig_user_id']. */
    public function igUserIdFor(SocialAccount $account): string
    {
        $id = $account->external_id ?: (((array) $account->meta)['ig_user_id'] ?? null);
        if (! $id) {
            throw PublishException::permanent('Instagram account is missing its business user id. Reconnect the account.');
        }

        return (string) $id;
    }

    /** Create a REELS container. Returns the container (creation) id. */
    public function createReelContainer(string $igUserId, string $videoUrl, string $caption, string $token): string
    {
        $response = $this->send(fn () => Http::asForm()->post($this->graph()."/{$igUserId}/media", [
            'media_type' => 'REELS',
            'video_url' => $videoUrl,
            'caption' => $caption,
            'share_to_feed' => 'true',
            'access_token' => $token,
        ]));

        if (! $response->successful()) {
            throw PublishException::fromResponse('Instagram container create failed', $response);
        }

        $id = $response->json('id');
        if (! $id) {
            throw PublishException::retryable('Instagram did not return a container id.');
        }

        return (string) $id;
    }

    /**
     * Container processing status.
     *
     * @return array{status_code: ?string, status: ?string}
     */
    public function containerStatus(string $containerId, string $token): array
    {
        $response = $this->send(fn () => Http::get($this->graph()."/{$containerId}", [
            'fields' => 'status_code,status',
            'access_token' => $token,
        ]));

        if (! $response->successful()) {
            throw PublishException::fromResponse('Instagram container status failed', $response);
        }

        return [
            'status_code' => $response->json('status_code'),
            'status' => $response->json('status'),
        ];
    }

    /** Publish a FINISHED container. Returns the IG media id. */
    public function publishContainer(string $igUserId, string $containerId, string $token): string
    {
        $response = $this->send(fn () => Http::asForm()->post($this->graph()."/{$igUserId}/media_publish", [
            'creation_id' => $containerId,
            'access_token' => $token,
        ]));

        if (! $response->successful()) {
            throw PublishException::fromResponse('Instagram publish failed', $response);
        }

        $id = $response->json('id');
        if (! $id) {
            throw PublishException::retryable('Instagram publish returned no media id.');
        }

        return (string) $id;
    }

    /**
     * permalink + thumbnail_url of published media. Never throws (best effort).
     *
     * @return array{permalink: ?string, thumbnail_url: ?string}
     */
    public function mediaInfo(string $mediaId, string $token): array
    {
        try {
            $response = Http::get($this->graph()."/{$mediaId}", [
                'fields' => 'permalink,thumbnail_url',
                'access_token' => $token,
            ]);
        } catch (\Throwable) {
            return ['permalink' => null, 'thumbnail_url' => null];
        }

        return [
            'permalink' => $response->successful() ? $response->json('permalink') : null,
            'thumbnail_url' => $response->successful() ? $response->json('thumbnail_url') : null,
        ];
    }

    /**
     * Blocking end-to-end publish (smoke-test command only; jobs poll via release()).
     */
    public function publishReel(string $publicVideoUrl, ?string $caption, SocialAccount $account): string
    {
        $token = app(TokenManager::class)->freshAccessToken($account);
        $igUserId = $this->igUserIdFor($account);
        $containerId = $this->createReelContainer($igUserId, $publicVideoUrl, (string) $caption, $token);

        for ($i = 0; $i < 10; $i++) {
            $status = $this->containerStatus($containerId, $token);
            if ($status['status_code'] === 'FINISHED') {
                return $this->publishContainer($igUserId, $containerId, $token);
            }
            if (in_array($status['status_code'], ['ERROR', 'EXPIRED'], true)) {
                throw PublishException::permanent('Instagram container processing failed: '.($status['status'] ?? $status['status_code']));
            }
            Sleep::for(30)->seconds();
        }

        throw PublishException::retryable('Instagram container did not finish processing in time.');
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
        $pages = Http::get($this->graph().'/me/accounts', [
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
        $me = Http::get($this->graph().'/me', [
            'fields' => 'user_id,username',
            'access_token' => $accessToken,
        ]);

        if ($me->successful() && $me->json('user_id')) {
            return ['ig_user_id' => (string) $me->json('user_id'), 'flow' => 'instagram'];
        }

        return null;
    }

    private function send(callable $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException $e) {
            throw PublishException::retryable('Instagram connection error: '.$e->getMessage());
        }
    }
}
