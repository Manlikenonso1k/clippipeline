<?php

namespace App\Studio\Services;

use App\Models\User;
use App\Studio\Models\StudioAsset;
use App\Studio\Models\StudioProject;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Upload and add-by-link (STUDIO_SPEC §2 platform-link rule, §5). */
class AssetIntake
{
    public const PLATFORM_HOSTS = ['tiktok.com', 'instagram.com', 'youtube.com', 'youtu.be', 'facebook.com', 'fb.watch', 'x.com', 'twitter.com'];

    /** Accepted mimes per role. */
    public const MIMES = [
        'source' => ['video/mp4', 'video/quicktime', 'video/webm'],
        'reference' => ['video/mp4', 'video/quicktime', 'video/webm'],
        'music' => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/wav', 'audio/x-wav', 'audio/wave'],
        'image' => ['image/jpeg', 'image/png', 'image/webp'],
        'logo' => ['image/jpeg', 'image/png', 'image/webp'],
    ];

    public function __construct(private StudioFiles $files, private JobQueue $jobs) {}

    public function upload(User $user, StudioProject $project, UploadedFile $file, string $role): StudioAsset
    {
        $path = $this->files->putUpload($file, $this->files->dir($user->id, 'assets'));
        $absolute = $this->files->disk()->path($path);

        $asset = StudioAsset::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role,
            'origin' => 'upload',
            'original_name' => mb_substr($file->getClientOriginalName() ?: basename($path), 0, 250),
            'disk' => 'local',
            'path' => $path,
            'mime' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'sha256' => is_file($absolute) ? hash_file('sha256', $absolute) : null,
            'status' => 'processing',
        ]);

        $this->jobs->dispatch('probe', $user->id, $project->id, $asset->id, [], $role === 'reference' ? 0 : 5);

        return $asset;
    }

    /** @throws ValidationException */
    public function link(User $user, StudioProject $project, string $url, string $role): StudioAsset
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || $host === '') {
            throw ValidationException::withMessages(['url' => 'Use an https:// link.']);
        }

        $isPlatform = self::isPlatformHost($host);
        if ($isPlatform && config('clippipeline.studio.link_fetcher') !== 'ytdlp') {
            throw ValidationException::withMessages(['url' => "We can't download from that site. Save the video to your device and upload the file instead."]);
        }

        if (! $isPlatform) {
            $this->assertDirectMedia($url, $role);
        }

        $asset = StudioAsset::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role,
            'origin' => 'link',
            'original_url' => $url,
            'original_name' => mb_substr(basename((string) ($parts['path'] ?? '')) ?: $host, 0, 250),
            'disk' => 'local',
            'status' => 'fetching',
        ]);

        $this->jobs->dispatch('fetch_link', $user->id, $project->id, $asset->id, [
            'url' => $url,
            'ytdlp' => $isPlatform,
        ], 5);

        return $asset;
    }

    public static function isPlatformHost(string $host): bool
    {
        foreach (self::PLATFORM_HOSTS as $p) {
            if ($host === $p || str_ends_with($host, '.'.$p)) {
                return true;
            }
        }

        return false;
    }

    /** Direct links must answer HEAD with a media Content-Type for the role and fit the size limit. */
    private function assertDirectMedia(string $url, string $role): void
    {
        try {
            $head = Http::timeout(15)->head($url);
        } catch (Throwable) {
            throw ValidationException::withMessages(['url' => 'That link could not be reached.']);
        }
        $type = strtolower(trim(explode(';', (string) $head->header('Content-Type'))[0]));
        $family = explode('/', self::MIMES[$role][0])[0];
        if (! $head->successful() || ! str_starts_with($type, $family.'/')) {
            throw ValidationException::withMessages(['url' => "That link is not a direct {$family} file (got ".($type ?: 'no type').').']);
        }
        $size = (int) $head->header('Content-Length');
        $max = (int) config('clippipeline.studio.max_upload_mb', 500) * 1024 * 1024;
        if ($size > $max) {
            throw ValidationException::withMessages(['url' => 'That file is larger than '.config('clippipeline.studio.max_upload_mb').' MB.']);
        }
    }
}
