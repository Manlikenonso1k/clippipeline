<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'social_account_id',
        'title',
        'video_url',
        'published_at',
        'tiktok_id',
        'caption',
        'download_path',
        'original_url',
        'downloaded_at',
        'meta',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'downloaded_at' => 'datetime',
        'meta' => 'array',
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
}
