<?php

namespace App\Studio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StudioTrend extends Model
{
    public const PLATFORMS = ['tiktok', 'youtube', 'instagram'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['hashtags' => 'array', 'is_active' => 'boolean', 'expires_at' => 'datetime', 'score' => 'integer'];
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->where('is_active', true)->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
