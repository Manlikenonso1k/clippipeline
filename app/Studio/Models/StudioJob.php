<?php

namespace App\Studio\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudioJob extends Model
{
    use HasUuids;

    public const TYPES = ['probe', 'fetch_link', 'transcribe', 'analyze_reference', 'auto_cut', 'render_preview', 'render_final', 'render_template_preview', 'explainer'];

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELED = 'canceled';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'result' => 'array',
            'progress' => 'float',
            'priority' => 'integer',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'lease_expires_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(StudioProject::class, 'project_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(StudioAsset::class, 'asset_id');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED, self::STATUS_CANCELED], true);
    }
}
