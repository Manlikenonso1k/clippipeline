<?php

namespace App\Models;

use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_NEEDS_REAUTH = 'needs_reauth';

    public const STATUS_ERROR = 'error';

    /** Display/API order. */
    public const PLATFORMS = ['youtube', 'facebook', 'instagram', 'tiktok'];

    protected $fillable = [
        'user_id',
        'provider',
        'platform_name',
        'handle',
        'follower_count',
        'access_token',
        'refresh_token',
        'expires_at',
        'meta',
        'needs_attention',
        'status',
        'external_id',
        'display_name',
        'avatar_url',
        'last_synced_at',
        'last_error',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        // Encrypted at rest (APP_KEY). Existing rows were converted by the
        // 2026_10_13_100200_encrypt_social_account_tokens data migration.
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'last_authorized_check_at' => 'datetime',
        'reauth_required_at' => 'datetime',
        'follower_count' => 'integer',
        'meta' => 'array',
        'needs_attention' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /** OAuth flow that owns this row (facebook + instagram => meta). */
    public function connectProvider(): string
    {
        return in_array($this->provider, ['facebook', 'instagram'], true) ? 'meta' : (string) $this->provider;
    }

    /** Status as reported to clients, accounting for pre-go-live rows. */
    public function effectiveStatus(): string
    {
        $status = $this->status ?: self::STATUS_ACTIVE;

        if ($status !== self::STATUS_ACTIVE) {
            return $status;
        }

        if (empty($this->access_token) || ($this->isExpired() && ! $this->refresh_token)) {
            return self::STATUS_NEEDS_REAUTH;
        }

        return $this->needs_attention ? self::STATUS_ERROR : self::STATUS_ACTIVE;
    }
}
