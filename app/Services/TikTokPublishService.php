<?php

namespace App\Services;

use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * TikTok Content Posting API, Direct Post
 * (developers.tiktok.com/doc/content-posting-api-reference-direct-post):
 * creator_info/query -> video/init (FILE_UPLOAD or PULL_FROM_URL) -> PUT chunks ->
 * status/fetch until PUBLISH_COMPLETE. Unaudited apps may only post SELF_ONLY.
 */
class TikTokPublishService
{
    private const BASE = 'https://open.tiktokapis.com/v2';

    private const MB = 1048576;

    /**
     * @return array<string, mixed> creator info (privacy_level_options, max_video_post_duration_sec, ...)
     */
    public function creatorInfo(string $token): array
    {
        return $this->call('TikTok creator info', fn () => Http::withToken($token)
            ->asJson()
            ->post(self::BASE.'/post/publish/creator_info/query/', (object) []));
    }

    /** The configured privacy level, if this creator allows it. */
    public function choosePrivacy(array $creatorInfo): string
    {
        $wanted = (string) config('services.tiktok_posting.privacy_level', 'SELF_ONLY');
        $options = (array) ($creatorInfo['privacy_level_options'] ?? []);

        if ($options === [] || in_array($wanted, $options, true)) {
            return $wanted;
        }

        throw PublishException::permanent("TikTok does not allow privacy level {$wanted} for this account (allowed: ".implode(', ', $options).').');
    }

    /**
     * Chunk plan per the Media Transfer Guide: <=64 MB goes in one chunk; larger
     * files use 10 MB chunks with the remainder merged into the last one.
     *
     * @return array{chunk_size: int, total_chunk_count: int}
     */
    public function chunkPlan(int $size): array
    {
        if ($size <= 64 * self::MB) {
            return ['chunk_size' => $size, 'total_chunk_count' => 1];
        }

        $chunk = 10 * self::MB;

        return ['chunk_size' => $chunk, 'total_chunk_count' => intdiv($size, $chunk)];
    }

    /**
     * Init a direct post. Returns ['publish_id' => ..., 'upload_url' => ?string].
     *
     * @param  array<string, mixed>  $postInfo
     * @return array{publish_id: string, upload_url: ?string}
     */
    public function initDirectPost(string $token, array $postInfo, ?string $filePath, ?string $videoUrl): array
    {
        if ($filePath !== null) {
            $size = (int) filesize($filePath);
            $source = ['source' => 'FILE_UPLOAD', 'video_size' => $size] + $this->chunkPlan($size);
        } else {
            $source = ['source' => 'PULL_FROM_URL', 'video_url' => $videoUrl];
        }

        $data = $this->call('TikTok post init', fn () => Http::withToken($token)
            ->asJson()
            ->post(self::BASE.'/post/publish/video/init/', [
                'post_info' => $postInfo,
                'source_info' => $source,
            ]));

        if (empty($data['publish_id'])) {
            throw PublishException::retryable('TikTok did not return a publish_id.');
        }

        return ['publish_id' => (string) $data['publish_id'], 'upload_url' => $data['upload_url'] ?? null];
    }

    /** PUT the file to upload_url in sequential chunks. */
    public function uploadFile(string $uploadUrl, string $filePath): void
    {
        $size = (int) filesize($filePath);
        $plan = $this->chunkPlan($size);
        $mime = match (strtolower(pathinfo($filePath, PATHINFO_EXTENSION))) {
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            default => 'video/mp4',
        };

        for ($i = 0; $i < $plan['total_chunk_count']; $i++) {
            $first = $i * $plan['chunk_size'];
            $last = $i === $plan['total_chunk_count'] - 1 ? $size - 1 : $first + $plan['chunk_size'] - 1;
            $length = $last - $first + 1;

            $body = new LimitStream(Utils::streamFor(fopen($filePath, 'rb')), $length, $first);

            try {
                $response = Http::timeout(300)
                    ->withHeaders([
                        'Content-Length' => (string) $length,
                        'Content-Range' => "bytes {$first}-{$last}/{$size}",
                    ])
                    ->withBody($body, $mime)
                    ->put($uploadUrl);
            } catch (ConnectionException $e) {
                throw PublishException::retryable('TikTok upload connection error: '.$e->getMessage());
            } finally {
                $body->close();
            }

            if (! $response->successful()) {
                throw PublishException::fromResponse('TikTok upload failed', $response);
            }
        }
    }

    /**
     * @return array<string, mixed> status, fail_reason, publicaly_available_post_id
     */
    public function status(string $token, string $publishId): array
    {
        return $this->call('TikTok status', fn () => Http::withToken($token)
            ->asJson()
            ->post(self::BASE.'/post/publish/status/fetch/', ['publish_id' => $publishId]));
    }

    /**
     * cover_image_url + share_url via the Display API (needs video.list). Best effort.
     *
     * @return array{cover_image_url: ?string, share_url: ?string}
     */
    public function videoInfo(string $token, string $videoId): array
    {
        try {
            $response = Http::withToken($token)
                ->asJson()
                ->post(self::BASE.'/video/query/?fields=id,cover_image_url,share_url', [
                    'filters' => ['video_ids' => [$videoId]],
                ]);
            $video = $response->successful() ? (array) $response->json('data.videos.0', []) : [];
        } catch (\Throwable) {
            $video = [];
        }

        return [
            'cover_image_url' => $video['cover_image_url'] ?? null,
            'share_url' => $video['share_url'] ?? null,
        ];
    }

    /**
     * POST and return `data`, turning HTTP or `error.code != ok` into PublishException.
     *
     * @return array<string, mixed>
     */
    private function call(string $what, callable $request): array
    {
        try {
            /** @var Response $response */
            $response = $request();
        } catch (ConnectionException $e) {
            throw PublishException::retryable("{$what} connection error: ".$e->getMessage());
        }

        if (! $response->successful()) {
            throw PublishException::fromResponse($what.' failed', $response);
        }

        $code = $response->json('error.code');
        if ($code !== null && $code !== 'ok') {
            throw PublishException::permanent("{$what} failed: {$code} — ".$response->json('error.message'));
        }

        return (array) $response->json('data', []);
    }
}
