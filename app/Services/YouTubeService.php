<?php

namespace App\Services;

use Google_Client;
use Google_Service_YouTube;
use Google_Service_YouTube_Video;
use Google_Service_YouTube_VideoSnippet;
use Google_Service_YouTube_VideoStatus;
use Illuminate\Support\Facades\Log;

class YouTubeService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Google_Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        $this->client->setScopes([
            'https://www.googleapis.com/auth/youtube.upload',
            'https://www.googleapis.com/auth/yt-analytics.readonly',
        ]);
    }

    /**
     * Upload a short to YouTube using a refresh token. Returns published video ID or null on failure.
     */
    public function uploadShort(string $filePath, string $title, string $description, ?string $refreshToken): ?string
    {
        if (! $refreshToken) {
            Log::error('YouTubeService: missing refresh token');
            return null;
        }

        try {
            $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
            $accessToken = $this->client->getAccessToken();

            $youtube = new Google_Service_YouTube($this->client);

            $video = new Google_Service_YouTube_Video();
            $snippet = new Google_Service_YouTube_VideoSnippet();
            $snippet->setTitle($title);
            $snippet->setDescription($description);
            $snippet->setTags(['Shorts']);

            $status = new Google_Service_YouTube_VideoStatus();
            $status->setPrivacyStatus('public');

            $video->setSnippet($snippet);
            $video->setStatus($status);

            // Resumable upload
            $chunkSizeBytes = 1 * 1024 * 1024;
            $this->client->setDefer(true);

            $insertRequest = $youtube->videos->insert('snippet,status', $video);

            $media = new \Google_Http_MediaFileUpload(
                $this->client,
                $insertRequest,
                'video/*',
                null,
                true,
                $chunkSizeBytes
            );

            $media->setFileSize(filesize($filePath));

            $statusResp = false;
            $handle = fopen($filePath, 'rb');
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSizeBytes);
                $statusResp = $media->nextChunk($chunk);
            }
            fclose($handle);

            $this->client->setDefer(false);

            if ($statusResp && isset($statusResp['id'])) {
                return $statusResp['id'];
            }

            if (is_object($statusResp) && property_exists($statusResp, 'id')) {
                return $statusResp->id;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('YouTubeService upload error: '.$e->getMessage());
            return null;
        }
    }
}
