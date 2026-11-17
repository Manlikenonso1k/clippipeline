<?php

namespace App\Studio\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudioProject extends Model
{
    public const ASPECTS = ['9:16', '1:1', '16:9'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'edl' => 'array',
            'edl_previous' => 'array',
            'style_profile' => 'array',
            'revision' => 'integer',
            'fps' => 'integer',
            'last_opened_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(StudioAsset::class, 'project_id');
    }

    public function renders(): HasMany
    {
        return $this->hasMany(StudioRender::class, 'project_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(StudioJob::class, 'project_id');
    }

    public function lastRender(): BelongsTo
    {
        return $this->belongsTo(StudioRender::class, 'last_render_id');
    }
}
