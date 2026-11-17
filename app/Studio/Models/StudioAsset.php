<?php

namespace App\Studio\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudioAsset extends Model
{
    public const ROLES = ['source', 'reference', 'music', 'image', 'logo'];

    public const STATUSES = ['pending', 'fetching', 'processing', 'ready', 'failed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'probe' => 'array',
            'style_profile' => 'array',
            'has_audio' => 'boolean',
            'duration_seconds' => 'float',
            'fps' => 'float',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(StudioProject::class, 'project_id');
    }

    public function isVideo(): bool
    {
        return str_starts_with((string) $this->mime, 'video/');
    }

    public function isAudio(): bool
    {
        return str_starts_with((string) $this->mime, 'audio/');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    /** Every stored file of this asset (for deletion and pruning). */
    public function storedPaths(): array
    {
        return array_values(array_filter([
            $this->path, $this->preview_proxy_path, $this->poster_path,
            $this->waveform_path, $this->thumbs_path, $this->transcript_path,
        ]));
    }
}
