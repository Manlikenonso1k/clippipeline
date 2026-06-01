<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostDistribution extends Model
{
    protected $table = 'post_distributions';

    protected $fillable = ['post_id', 'platform', 'external_id', 'status', 'error_message'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
