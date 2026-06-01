<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = [
        'user_id',
        'tiktok_id',
        'caption',
        'download_path',
        'original_url',
        'downloaded_at',
        'meta',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
