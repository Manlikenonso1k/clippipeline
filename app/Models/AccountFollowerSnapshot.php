<?php

namespace App\Models;

use Database\Factories\AccountFollowerSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Daily follower count per social account (captured_on is a 'Y-m-d' UTC string). */
class AccountFollowerSnapshot extends Model
{
    /** @use HasFactory<AccountFollowerSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'social_account_id',
        'platform',
        'followers',
        'captured_on',
    ];

    protected $casts = [
        'followers' => 'integer',
    ];

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
