<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TikTokScraperService
{
    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.tiktok_scraper.url') ?? env('TIKTOK_SCRAPER_URL');
        $this->apiKey = config('services.tiktok_scraper.key') ?? env('TIKTOK_SCRAPER_KEY');
    }

    // Returns latest video id for a username or null
    public function getLatestVideoId(string $username): ?string
    {
        if (! $this->baseUrl) {
            return null;
        }

        $resp = Http::withHeaders(['x-api-key' => $this->apiKey])->get($this->baseUrl.'/latest', ['username' => $username]);
        if (! $resp->ok()) {
            return null;
        }

        $data = $resp->json();
        return $data['video_id'] ?? null;
    }

    // Download video by id and return local storage path
    public function downloadVideo(string $videoId): ?string
    {
        if (! $this->baseUrl) {
            return null;
        }

        $resp = Http::withHeaders(['x-api-key' => $this->apiKey])->get($this->baseUrl.'/download', ['video_id' => $videoId]);
        if (! $resp->ok()) {
            return null;
        }

        $data = $resp->json();
        $downloadUrl = $data['download_url'] ?? null;
        if (! $downloadUrl) {
            return null;
        }

        $filename = 'videos/'.now()->format('Ymd_His').'_'.$videoId.'.mp4';
        $localPath = storage_path('app/'.$filename);

        try {
            Http::withOptions(['sink' => $localPath])->get($downloadUrl);
            // Return storage relative path
            return $filename;
        } catch (\Exception $e) {
            return null;
        }
    }
}
