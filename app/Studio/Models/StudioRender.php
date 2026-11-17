<?php

namespace App\Studio\Models;

use App\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudioRender extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'float',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'edl_revision' => 'integer',
            'check' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(StudioProject::class, 'project_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
