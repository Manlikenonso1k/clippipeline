<?php

namespace App\Models;

use Database\Factories\PostAnalyticFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostAnalytic extends Model
{
    /** @use HasFactory<PostAnalyticFactory> */
    use HasFactory;

    protected $table = 'post_analytics';

    protected $fillable = [
        'post_id',
        'platform',
        'views',
        'likes',
        'comments',
        'shares',
        'watch_time',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'watch_time' => 'integer',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function getTotalEngagement(): int
    {
        return $this->views + $this->likes + $this->comments;
    }
}
