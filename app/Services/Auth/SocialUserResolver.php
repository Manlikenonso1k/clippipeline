<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Find-or-create a user from a social login. Same matching as the existing
 * /auth/{google,facebook,tiktok}/token endpoints (shared with the sign-in broker).
 */
class SocialUserResolver
{
    /**
     * Match by google_id, then link an existing account by verified email, then create
     * (USER_SIM BUG-1: a creator who registered with email/Facebook can use Google).
     */
    public function google(string $id, string $email, ?string $name, ?string $avatar, bool $emailVerified = true): User
    {
        $user = User::where('google_id', $id)->first();
        if (! $user && $emailVerified && $email !== '') {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            $user->fill([
                'google_id' => $id,
                'name' => $name ?: $user->name,
                'avatar' => $avatar ?: $user->avatar,
            ]);
            // Keep the Google email in sync unless another account already owns it.
            if ($emailVerified && $email !== '' && ! User::where('email', $email)->whereKeyNot($user->getKey())->exists()) {
                $user->email = $email;
            }
            $user->save();

            return $user;
        }

        if (User::where('email', $email)->exists()) {
            throw new RuntimeException('This Google email is not verified and already belongs to another ClipPipeline account.');
        }

        return User::create([
            'google_id' => $id,
            'name' => $name ?: 'Google User',
            'email' => $email,
            'avatar' => $avatar,
            'password' => Hash::make(Str::random(24)),
        ]);
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
