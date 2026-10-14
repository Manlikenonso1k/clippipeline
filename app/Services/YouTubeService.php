<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Services\Connect\TokenManager;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * YouTube Data API v3 videos.insert via the resumable upload protocol
 * (developers.google.com/youtube/v3/guides/using_resumable_upload_protocol).
 * The file is streamed from disk, never loaded into memory.
 */
class YouTubeService
{
    private const UPLOAD_URL = 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status';

    /**
     * Step 1: start a resumable session. Returns the session URI (Location header).
     *
     * @param  array<int, string>  $tags
     */
    public function startSession(
        string $accessToken,
        string $filePath,
        string $title,
        string $description,
        array $tags,
        string $privacyStatus,
    ): string {
        $this->assertFile($filePath);

        $response = $this->send(fn () => Http::withToken($accessToken)
            ->withHeaders([
                'X-Upload-Content-Length' => (string) filesize($filePath),
                'X-Upload-Content-Type' => $this->mime($filePath),
            ])
            ->asJson()
            ->post(self::UPLOAD_URL, [
                'snippet' => [
                    'title' => mb_substr(str_replace(['<', '>'], '', $title), 0, 100), // P-02: no angle brackets
                    'description' => mb_strcut(str_replace(['<', '>'], '', $description), 0, 5000), // 5000 bytes
                    'tags' => array_values(array_unique(array_merge(['Shorts'], $tags))),
                    'categoryId' => '22',
                ],
                'status' => [
                    'privacyStatus' => $privacyStatus,
                    'selfDeclaredMadeForKids' => false,
                ],
            ]));

        if (! $response->successful()) {
            throw $this->limitError($response) ?? PublishException::fromResponse('YouTube upload session failed', $response);
        }

        $location = $response->header('Location');
        if (! $location) {
            throw PublishException::retryable('YouTube did not return an upload session URL.');
        }

        return $location;
    }

    /**
     * Step 2: send the bytes (resuming from what YouTube already has when $resume).
     * Returns the created video resource.
     *
     * @return array<string, mixed>
     *
     * @throws YouTubeSessionExpired when the session URI is gone (404)
     */
    public function uploadToSession(string $sessionUri, string $accessToken, string $filePath, bool $resume = false): array
    {
        $this->assertFile($filePath);
        $size = (int) filesize($filePath);
        $mime = $this->mime($filePath);
        $offset = 0;

        if ($resume) {
            $status = $this->send(fn () => Http::withToken($accessToken)
                ->withHeaders(['Content-Range' => "bytes */{$size}"])
                ->withBody('', $mime)
                ->put($sessionUri));

            if (in_array($status->status(), [200, 201], true) && $status->json('id')) {
                return (array) $status->json();
            }
            if ($status->status() === 404) {
                throw new YouTubeSessionExpired('YouTube upload session expired; starting a new one.');
            }
            if ($status->status() === 308 && preg_match('/bytes=0-(\d+)/', (string) $status->header('Range'), $m)) {
                $offset = ((int) $m[1]) + 1;
            }
        }

        $handle = fopen($filePath, 'rb');
        if ($offset > 0) {
            fseek($handle, $offset);
        }

        $headers = ['Content-Length' => (string) ($size - $offset)];
        if ($offset > 0) {
            $headers['Content-Range'] = 'bytes '.$offset.'-'.($size - 1).'/'.$size;
        }

        $stream = Utils::streamFor($handle);
        try {
            $response = $this->send(fn () => Http::withToken($accessToken)
                ->timeout(480)
                ->withHeaders($headers)
                ->withBody($stream, $mime)
                ->put($sessionUri));
        } finally {
            $stream->close();
        }

        if ($response->status() === 404) {
            throw new YouTubeSessionExpired('YouTube upload session expired; starting a new one.');
        }

        if ($response->status() === 308) {
            throw PublishException::retryable('YouTube upload incomplete; will resume.');
        }

        if (! $response->successful()) {
            throw $this->limitError($response) ?? PublishException::fromResponse('YouTube upload failed', $response);
        }

        if (! $response->json('id')) {
            throw PublishException::retryable('YouTube upload returned no video id.');
        }

        return (array) $response->json();
    }

    /**
     * COMPLIANCE P-03: the project's daily upload quota or the channel's upload limit
     * becomes a friendly "limit reached" error instead of the raw API text.
     */
    public function limitError(Response $response): ?PublishException
    {
        $reasons = collect((array) $response->json('error.errors', []))->pluck('reason')->filter()->all();

        if (array_intersect($reasons, ['quotaExceeded', 'dailyLimitExceeded', 'rateLimitExceeded', 'userRateLimitExceeded']) !== []) {
            return PublishException::permanent("YouTube limit reached: ClipPipeline's daily YouTube upload quota is used up. Retry this post tomorrow.");
        }
        if (in_array('uploadLimitExceeded', $reasons, true)) {
            return PublishException::permanent('YouTube limit reached: this channel has hit its upload limit for now. Retry later.');
        }

        return null;
    }

    /** Best thumbnail URL from a video resource (falls back to the standard i.ytimg URL). */
    public function thumbnailFrom(array $video): ?string
    {
        $thumbs = (array) ($video['snippet']['thumbnails'] ?? []);
        foreach (['maxres', 'standard', 'high', 'medium', 'default'] as $size) {
            if (! empty($thumbs[$size]['url'])) {
                return $thumbs[$size]['url'];
            }
        }

        return isset($video['id']) ? 'https://i.ytimg.com/vi/'.$video['id'].'/hqdefault.jpg' : null;
    }

    /** One-shot upload (used by the smoke-test command). Returns the video id. */
    public function uploadShort(
        string $filePath,
        string $title,
        string $description,
        SocialAccount $account,
        string $privacyStatus = 'private',
    ): string {
        $token = app(TokenManager::class)->freshAccessToken($account);
        $uri = $this->startSession($token, $filePath, $title, $description, [], $privacyStatus);

        return (string) $this->uploadToSession($uri, $token, $filePath)['id'];
    }

    private function send(callable $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException $e) {
            throw PublishException::retryable('YouTube connection error: '.$e->getMessage());
        }
    }

    private function assertFile(string $filePath): void
    {
        if (! is_file($filePath)) {
            throw PublishException::permanent("Video file not found: {$filePath}");
        }
    }

    private function mime(string $filePath): string
    {
        return match (strtolower(pathinfo($filePath, PATHINFO_EXTENSION))) {
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            default => 'video/mp4',
        };
    }
}
