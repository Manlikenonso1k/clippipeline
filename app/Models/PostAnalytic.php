<?php

namespace App\Models;

use Database\Factories\PostAnalyticFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per distribution per day (captured_on, UTC date string) holding the
 * LIFETIME counters the platform reported that day. See docs/CONTRACT.md §5.
 */
class PostAnalytic extends Model
{
    /** @use HasFactory<PostAnalyticFactory> */
    use HasFactory;

    protected $table = 'post_analytics';

    /** Counter columns that make up the contract's Metrics object. */
    public const METRICS = ['views', 'likes', 'comments', 'shares', 'saves', 'watch_time_seconds'];

    protected $fillable = [
        'post_id',
        'distribution_id',
        'platform',
        'views',
        'likes',
        'comments',
        'shares',
        'saves',
        'watch_time',
        'watch_time_seconds',
        'avg_view_duration_seconds',
        'captured_on',
        'recorded_at',
    ];

    // captured_on stays a plain 'Y-m-d' string so lookups match on every driver.
    protected $casts = [
        'recorded_at' => 'datetime',
        'views' => 'integer',
        'likes' => 'integer',
        'comments' => 'integer',
        'shares' => 'integer',
        'saves' => 'integer',
        'watch_time' => 'integer',
        'watch_time_seconds' => 'integer',
        'avg_view_duration_seconds' => 'integer',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(PostDistribution::class, 'distribution_id');
    }

    public function getTotalEngagement(): int
    {
        return $this->views + $this->likes + $this->comments;
    }
}
