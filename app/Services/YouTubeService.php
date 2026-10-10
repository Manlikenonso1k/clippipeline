<?php

namespace App\Services;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Uploads Shorts to YouTube via the Data API v3 using Laravel's HTTP client
 * (so it is mockable with Http::fake in tests and does not depend on the heavy
 * google/apiclient Guzzle stack). Handles access-token refresh via the stored
 * refresh token (item 7).
 */
class YouTubeService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const UPLOAD_URL = 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status';

    /**
     * Upload a short. Returns the YouTube video id.
     *
     * @throws RuntimeException on any failure (message is surfaced to the UI)
     */
    public function uploadShort(
        string $filePath,
        string $title,
        string $description,
        SocialAccount $account,
        string $privacyStatus = 'public',
    ): string {
        if (! is_file($filePath)) {
            throw new RuntimeException("Video file not found: {$filePath}");
        }

        $accessToken = $this->accessTokenFor($account);

        // 1. Initiate a resumable upload session.
        $init = Http::withToken($accessToken)
            ->withHeaders(['X-Upload-Content-Type' => 'video/*'])
            ->post(self::UPLOAD_URL, [
                'snippet' => [
                    'title' => mb_substr($title, 0, 100),
                    'description' => $description,
                    'tags' => ['Shorts'],
                ],
                'status' => [
                    'privacyStatus' => $privacyStatus,
                    'selfDeclaredMadeForKids' => false,
                ],
            ]);

        if (! $init->successful()) {
            throw new RuntimeException('YouTube init failed: '.$init->body());
        }

        $location = $init->header('Location');
        if (! $location) {
            throw new RuntimeException('YouTube did not return an upload URL.');
        }

        // 2. Upload the bytes.
        $uploaded = Http::withToken($accessToken)
            ->withBody(file_get_contents($filePath), 'video/*')
            ->put($location);

        if (! $uploaded->successful()) {
            throw new RuntimeException('YouTube upload failed: '.$uploaded->body());
        }

        $id = $uploaded->json('id');
        if (! $id) {
            throw new RuntimeException('YouTube upload returned no video id.');
        }

        return $id;
    }

    /**
     * Return a valid access token, refreshing and persisting it when expired.
     */
    public function accessTokenFor(SocialAccount $account): string
    {
        if ($account->access_token && ! $account->isExpired()) {
            return $account->access_token;
        }

        if (! $account->refresh_token) {
            throw new RuntimeException('YouTube account has no refresh token; reconnect the account.');
        }

        $resp = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $account->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $resp->successful()) {
            throw new RuntimeException('YouTube token refresh failed: '.$resp->body());
        }

        $token = $resp->json('access_token');
        if (! $token) {
            throw new RuntimeException('YouTube token refresh returned no access token.');
        }

        $account->forceFill([
            'access_token' => $token,
            'expires_at' => now()->addSeconds((int) $resp->json('expires_in', 3500)),
        ])->save();

        return $token;
    }
}
