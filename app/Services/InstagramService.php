<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class InstagramService
{
    /**
     * Publish a reel using the Instagram Graph API.
     * Expects $accountMeta to contain an 'instagram_business_account' or 'ig_user_id'.
     * Returns external id on success or null.
     */
    public function publishReel(string $localPath, ?string $caption, array $accountMeta, string $accessToken): ?string
    {
        try {
            // Ensure the file is publicly accessible: store on the public disk and get URL
            $stored = Storage::disk('public')->putFile('reels', storage_path('app/'.$localPath));
            if (! $stored) {
                Log::error('InstagramService: failed to store file to public disk');
                return null;
            }

            $publicUrl = Storage::disk('public')->url($stored);

            $igUserId = $accountMeta['instagram_business_account']['id'] ?? $accountMeta['ig_user_id'] ?? null;
            if (! $igUserId) {
                Log::error('InstagramService: missing ig user id in account meta');
                return null;
            }

            // Step 1: create container
            $createResp = Http::asForm()->post("https://graph.facebook.com/v13.0/{$igUserId}/media", [
                'video_url' => $publicUrl,
                'caption' => $caption ?? '',
                'access_token' => $accessToken,
            ]);

            if (! $createResp->ok()) {
                Log::error('InstagramService create container failed: '.$createResp->body());
                return null;
            }

            $data = $createResp->json();
            $creationId = $data['id'] ?? null;
            if (! $creationId) {
                Log::error('InstagramService: no creation id returned');
                return null;
            }

            // Step 2: publish
            $publishResp = Http::asForm()->post("https://graph.facebook.com/v13.0/{$igUserId}/media_publish", [
                'creation_id' => $creationId,
                'access_token' => $accessToken,
            ]);

            if (! $publishResp->ok()) {
                Log::error('InstagramService publish failed: '.$publishResp->body());
                return null;
            }

            $pubData = $publishResp->json();
            return $pubData['id'] ?? null;
        } catch (\Exception $e) {
            Log::error('InstagramService error: '.$e->getMessage());
            return null;
        }
    }
}
