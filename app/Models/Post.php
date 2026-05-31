<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
