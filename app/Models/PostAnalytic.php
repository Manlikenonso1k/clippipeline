<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostAnalytic extends Model
{
    protected $table = 'post_analytics';

    protected $fillable = ['post_id', 'platform', 'views', 'likes', 'comments', 'recorded_at'];

    protected $casts = [
        'recorded_at' => 'datetime',
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
