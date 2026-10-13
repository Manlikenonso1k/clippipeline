<?php

namespace App\Services;

use App\Models\SocialAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Facebook Page Reels (developers.facebook.com/docs/video-api/guides/reels-publishing):
 * POST /{page-id}/video_reels upload_phase=start -> POST rupload.facebook.com with a
 * file_url header (Meta fetches the public URL) -> GET /{video-id}?fields=status ->
 * POST /{page-id}/video_reels upload_phase=finish video_state=PUBLISHED.
 */
class FacebookService
{
    private function version(): string
    {
        return (string) config('services.meta.graph_version', 'v24.0');
    }

    private function graph(): string
    {
        return 'https://graph.facebook.com/'.$this->version();
    }

    /** Page id: social_accounts.external_id, falling back to meta['page_id']. */
    public function pageIdFor(SocialAccount $account): string
    {
        $id = $account->external_id ?: (((array) $account->meta)['page_id'] ?? null);
        if (! $id) {
            throw PublishException::permanent('Facebook account is missing its Page id. Reconnect the account.');
        }

        return (string) $id;
    }

    /** Start an upload session. Returns the video id. */
    public function startReel(string $pageId, string $pageToken): string
    {
        $response = $this->send(fn () => Http::asForm()->post($this->graph()."/{$pageId}/video_reels", [
            'upload_phase' => 'start',
            'access_token' => $pageToken,
        ]));

        if (! $response->successful()) {
            throw PublishException::fromResponse('Facebook Reel start failed', $response);
        }

        $videoId = $response->json('video_id');
        if (! $videoId) {
            throw PublishException::retryable('Facebook did not return a video id.');
        }

        return (string) $videoId;
    }

    /** Hand Meta the hosted file URL. */
    public function uploadHosted(string $videoId, string $videoUrl, string $pageToken): void
    {
        $response = $this->send(fn () => Http::withHeaders([
            'Authorization' => 'OAuth '.$pageToken,
            'file_url' => $videoUrl,
        ])->post('https://rupload.facebook.com/video-upload/'.$this->version().'/'.$videoId));

        if (! $response->successful() || $response->json('success') === false) {
            throw PublishException::fromResponse('Facebook Reel upload failed', $response);
        }
    }

    /**
     * @return array<string, mixed> the `status` object (video_status, *_phase)
     */
    public function status(string $videoId, string $pageToken): array
    {
        $response = $this->send(fn () => Http::get($this->graph()."/{$videoId}", [
            'fields' => 'status',
            'access_token' => $pageToken,
        ]));

        if (! $response->successful()) {
            throw PublishException::fromResponse('Facebook Reel status failed', $response);
        }

        return (array) $response->json('status', []);
    }

    public function finishReel(string $pageId, string $videoId, string $description, string $pageToken): void
    {
        $response = $this->send(fn () => Http::asForm()->post($this->graph()."/{$pageId}/video_reels", [
            'upload_phase' => 'finish',
            'video_id' => $videoId,
            'video_state' => 'PUBLISHED',
            'description' => $description,
            'access_token' => $pageToken,
        ]));

        if (! $response->successful() || $response->json('success') === false) {
            throw PublishException::fromResponse('Facebook Reel publish failed', $response);
        }
    }

    /**
     * permalink_url + picture of the published reel. Never throws (best effort).
     *
     * @return array{permalink: ?string, picture: ?string, post_id: ?string}
     */
    public function videoInfo(string $videoId, string $pageToken): array
    {
        try {
            $response = Http::get($this->graph()."/{$videoId}", [
                'fields' => 'permalink_url,picture,post_id',
                'access_token' => $pageToken,
            ]);
        } catch (\Throwable) {
            return ['permalink' => null, 'picture' => null, 'post_id' => null];
        }

        $permalink = $response->successful() ? $response->json('permalink_url') : null;
        if (is_string($permalink) && str_starts_with($permalink, '/')) {
            $permalink = 'https://www.facebook.com'.$permalink;
        }

        return [
            'permalink' => $permalink,
            'picture' => $response->successful() ? $response->json('picture') : null,
            'post_id' => $response->successful() && $response->json('post_id') ? (string) $response->json('post_id') : null,
        ];
    }

    private function send(callable $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException $e) {
            throw PublishException::retryable('Facebook connection error: '.$e->getMessage());
        }
    }
}
