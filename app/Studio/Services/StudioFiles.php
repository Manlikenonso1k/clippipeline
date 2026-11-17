<?php

namespace App\Studio\Services;

use App\Studio\Models\StudioAsset;
use App\Studio\Models\StudioRender;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Studio files live on the private `local` disk under studio/{user_id}/... and are
 * served only through temporary signed URLs (route studio.file). Template gallery
 * previews are the exception: they go on the `public` disk so guests can see them.
 */
class StudioFiles
{
    /** Asset variants => model column. */
    public const ASSET_VARIANTS = [
        'original' => 'path',
        'proxy' => 'preview_proxy_path',
        'poster' => 'poster_path',
        'waveform' => 'waveform_path',
        'thumbs' => 'thumbs_path',
        'transcript' => 'transcript_path',
    ];

    public const RENDER_VARIANTS = ['video' => 'path', 'poster' => 'poster_path'];

    public function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    public function dir(int $userId, string ...$parts): string
    {
        return implode('/', array_merge(['studio', (string) $userId], $parts));
    }

    public function putUpload(UploadedFile $file, string $dir, ?string $name = null): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $name ??= Str::uuid().'.'.$ext;

        return $this->disk()->putFileAs($dir, $file, $name) ?: throw new \RuntimeException('Could not store file.');
    }

    public function signedUrl(string $kind, int $id, string $variant, ?int $minutes = null, array $extra = []): string
    {
        $minutes ??= (int) config('clippipeline.studio.signed_url_minutes', 120);

        return URL::temporarySignedRoute('studio.file', now()->addMinutes($minutes), [
            'kind' => $kind, 'id' => $id, 'variant' => $variant,
        ] + $extra);
    }

    public function assetUrl(StudioAsset $asset, string $variant, ?int $minutes = null): ?string
    {
        $column = self::ASSET_VARIANTS[$variant] ?? null;

        return $column && $asset->{$column} ? $this->signedUrl('asset', $asset->id, $variant, $minutes) : null;
    }

    public function renderUrl(StudioRender $render, string $variant, ?int $minutes = null, bool $download = false): ?string
    {
        $column = self::RENDER_VARIANTS[$variant] ?? null;

        return $column && $render->{$column} ? $this->signedUrl('render', $render->id, $variant, $minutes, $download ? ['download' => 1] : []) : null;
    }

    public function delete(array $paths): void
    {
        $paths = array_values(array_filter($paths));
        if ($paths) {
            $this->disk()->delete($paths);
        }
    }

    public function publicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        $url = Storage::disk('public')->url($path);
        if (! preg_match('#^https?://#i', $url)) {
            $url = rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
        }

        return $url;
    }
}
