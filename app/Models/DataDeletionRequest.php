<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A deletion request + PII-free audit row (COMPLIANCE C-06, A-02). */
class DataDeletionRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = ['code', 'source', 'subject_hash', 'status', 'summary', 'completed_at'];

    protected $casts = [
        'summary' => 'array',
        'completed_at' => 'datetime',
    ];

    public static function open(string $source, ?string $subject): self
    {
        return self::create([
            'code' => Str::upper(Str::random(16)),
            'source' => $source,
            'subject_hash' => $subject !== null ? hash('sha256', $source.':'.$subject) : null,
            'status' => self::STATUS_PENDING,
        ]);
    }

    /** @param  array<string, int>  $summary  counts only, never PII */
    public function complete(array $summary = []): void
    {
        $this->forceFill([
            'status' => self::STATUS_COMPLETED,
            'summary' => $summary,
            'completed_at' => now(),
        ])->save();
    }
}
