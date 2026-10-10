<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostDistribution extends Model
{
    protected $table = 'post_distributions';

    protected $fillable = [
        'post_id',
        'platform',
        'external_id',
        'status',
        'error',
        'error_message',
        'published_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'published_at' => 'datetime',
    ];

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', self::STATUS_QUEUED], true);
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /** Public URL to the published item, when known. */
    public function externalUrl(): ?string
    {
        if (! $this->external_id) {
            return null;
        }

        return match ($this->platform) {
            'youtube' => 'https://www.youtube.com/watch?v='.$this->external_id,
            'instagram' => 'https://www.instagram.com/reel/'.$this->external_id,
            'facebook' => 'https://www.facebook.com/'.$this->external_id,
            'tiktok' => 'https://www.tiktok.com/@me/video/'.$this->external_id,
            default => null,
        };
    }
}
