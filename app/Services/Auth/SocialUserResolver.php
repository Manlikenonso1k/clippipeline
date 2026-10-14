<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Find-or-create a user from a social login. Same matching as the existing
 * /auth/{google,facebook,tiktok}/token endpoints (shared with the sign-in broker).
 */
class SocialUserResolver
{
    public function google(string $id, string $email, ?string $name, ?string $avatar): User
    {
        $user = User::firstOrCreate(
            ['google_id' => $id],
            [
                'name' => $name ?: 'Google User',
                'email' => $email,
                'avatar' => $avatar,
                'password' => Hash::make(Str::random(24)),
            ],
        );

        $user->fill([
            'name' => $name ?: $user->name,
            'email' => $email,
            'avatar' => $avatar ?: $user->avatar,
        ])->save();

        return $user;
    }

    public function facebook(string $id, ?string $email, ?string $name, ?string $avatar): User
    {
        // Match by facebook_id first, then link to an existing account by email.
        $user = User::where('facebook_id', $id)->first();
        if (! $user && $email) {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            $user->fill([
                'facebook_id' => $id,
                'name' => $name ?: $user->name,
                'avatar' => $avatar ?: $user->avatar,
            ])->save();

            return $user;
        }

        return User::create([
            'facebook_id' => $id,
            'name' => $name ?: 'Facebook User',
            'email' => $email ?: 'fb_'.$id.'@users.clippipeline.app',
            'avatar' => $avatar,
            'password' => Hash::make(Str::random(24)),
        ]);
    }

    /** TikTok returns no email: keyed on open_id with a placeholder email. */
    public function tiktok(string $openId, ?string $name, ?string $avatar): User
    {
        $user = User::where('tiktok_id', $openId)->first();

        if ($user) {
            $user->fill([
                'name' => $name ?: $user->name,
                'avatar' => $avatar ?: $user->avatar,
            ])->save();

            return $user;
        }

        return User::create([
            'tiktok_id' => $openId,
            'name' => $name ?: 'TikTok User',
            'email' => 'tiktok_'.$openId.'@users.clippipeline.app',
            'avatar' => $avatar,
            'password' => Hash::make(Str::random(24)),
        ]);
    }
}
