<?php

namespace App\Studio\Services;

use App\Models\User;
use Illuminate\Support\Str;

/** The non-human user that owns the render worker token and system jobs (template previews). */
class ServiceUser
{
    public const EMAIL = 'studio-worker@clippipeline.invalid';

    public static function get(): User
    {
        return User::query()->firstOrCreate(['email' => self::EMAIL], [
            'name' => 'Studio render worker',
            'password' => Str::random(64),
            'is_admin' => false,
            'notifications_enabled' => false,
        ]);
    }
}
