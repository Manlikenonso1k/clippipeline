<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Resolves a Post's video into the two forms the publishers need: a public HTTPS
 * URL (Instagram/Facebook) and a local file path (YouTube upload). Videos are
 * stored on the `public` disk so they get an absolute HTTPS URL via storage:link.
 */
class VideoResolver
{
    /** Public HTTPS URL for the post's video, or null if none can be produced. */
    public static function publicUrl(Post $post): ?string
    {
        if (! empty($post->download_path) && Storage::disk('public')->exists($post->download_path)) {
            return self::absolute(Storage::disk('public')->url($post->download_path));
        }

        foreach ([$post->video_url, $post->original_url] as $candidate) {
            if (is_string($candidate) && str_starts_with($candidate, 'https://')) {
                return $candidate;
            }
        }

        return null;
    }

    /** Local filesystem path to the video, downloading a remote URL if needed. */
    public static function localPath(Post $post): string
    {
        if (! empty($post->download_path) && Storage::disk('public')->exists($post->download_path)) {
            return Storage::disk('public')->path($post->download_path);
        }

        $url = self::publicUrl($post);
        if (! $url) {
            throw new RuntimeException('No video source available for this post (upload a file or set a public URL).');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'clip_').'.mp4';
        $resp = Http::timeout(120)->sink($tmp)->get($url);
        if (! $resp->successful()) {
            throw new RuntimeException("Failed to download video from {$url} (HTTP {$resp->status()}).");
        }

        return $tmp;
    }

    private static function absolute(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return str_replace('http://', 'https://', $url);
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }
}
