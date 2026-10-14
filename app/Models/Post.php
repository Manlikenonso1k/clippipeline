<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_TIKTOK_INGEST = 'tiktok_ingest';

    /** docs/CONTRACT.md §1 PostStatus. */
    public const STATUSES = ['scheduled', 'processing', 'published', 'partial', 'failed'];

    protected $fillable = [
        'user_id',
        'social_account_id',
        'title',
        'video_url',
        'video_path',
        'thumbnail',
        'published_at',
        'scheduled_at',
        'tiktok_id',
        'caption',
        'hashtags',
        'source',
        'download_path',
        'original_url',
        'downloaded_at',
        'meta',
        'video_duration_seconds',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'downloaded_at' => 'datetime',
        'hashtags' => 'array',
        'meta' => 'array',
        'video_duration_seconds' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(PostDistribution::class);
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(PostAnalytic::class);
    }

    public function getCombinedEngagement(): int
    {
        return $this->analytics()
            ->selectRaw('SUM(views + likes + comments) as total')
            ->value('total') ?? 0;
    }

    /** Post status derived from its distributions (docs/CONTRACT.md §1). */
    public function overallStatus(): string
    {
        $statuses = $this->distributions->pluck('status')->map(fn ($s) => (string) $s === 'pending' ? PostDistribution::STATUS_QUEUED : (string) $s);

        if ($statuses->isEmpty()) {
            return 'processing';
        }

        if ($this->scheduled_at && $this->scheduled_at->isFuture()
            && $statuses->every(fn ($s) => $s === PostDistribution::STATUS_QUEUED)) {
            return 'scheduled';
        }

        if ($statuses->contains(fn ($s) => in_array($s, PostDistribution::IN_FLIGHT, true))) {
            return 'processing';
        }

        if ($statuses->every(fn ($s) => $s === PostDistribution::STATUS_PUBLISHED)) {
            return 'published';
        }

        if ($statuses->every(fn ($s) => $s === PostDistribution::STATUS_FAILED)) {
            return 'failed';
        }

        return 'partial';
    }

    /** Hashtags without '#', always an array. */
    public function hashtagList(): array
    {
        return array_values(array_filter(array_map(
            fn ($t) => ltrim(trim((string) $t), '#'),
            (array) ($this->hashtags ?? []),
        )));
    }

    /** "#a #b" for captions/descriptions. */
    public function hashtagLine(): string
    {
        return implode(' ', array_map(fn ($t) => '#'.$t, $this->hashtagList()));
    }

    /** Relative path of the video on the public disk (uploads, then TikTok downloads). */
    public function storedVideoPath(): ?string
    {
        foreach ([$this->video_path, $this->download_path] as $path) {
            if (! empty($path) && Storage::disk('public')->exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
