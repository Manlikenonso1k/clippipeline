<?php

namespace App\Models;

use App\Services\ExpoPushService;
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

    protected static function booted(): void
    {
        // Push the post's owner when a distribution finishes — failures first.
        static::saved(function (PostDistribution $distribution) {
            $changed = $distribution->wasRecentlyCreated || $distribution->wasChanged('status');
            if (! $changed) {
                return;
            }

            $user = $distribution->post?->user;
            if (! $user) {
                return;
            }

            $platform = ucfirst((string) $distribution->platform);
            $push = app(ExpoPushService::class);

            if ($distribution->status === self::STATUS_FAILED) {
                $push->sendToUser(
                    $user,
                    'Post failed to publish',
                    "We couldn't publish your clip to {$platform}. Tap to review.",
                    ['type' => 'distribution_failed', 'screen' => 'post', 'postId' => $distribution->post_id],
                    'alerts',
                );
            } elseif ($distribution->status === self::STATUS_PUBLISHED) {
                $push->sendToUser(
                    $user,
                    'Clip published',
                    "Your clip is now live on {$platform}.",
                    ['type' => 'distribution_published', 'screen' => 'post', 'postId' => $distribution->post_id],
                    'published',
                );
            }
        });
    }

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
