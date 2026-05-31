<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class IntegrationController
{
    // Redirect for main login (e.g., google)
    public function redirectToProvider(Request $request, $provider)
    {
        if ($provider !== 'google') {
            abort(404);
        }

        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    // Callback for main login
    public function handleProviderCallback(Request $request, $provider)
    {
        if ($provider !== 'google') {
            abort(404);
        }

        $socialUser = Socialite::driver('google')->stateless()->user();

        $user = User::firstOrCreate(
            ['google_id' => $socialUser->getId()],
            [
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'Google User',
                'email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
                // ensure non-null password for DB constraints when creating via OAuth
                'password' => Hash::make(Str::random(24)),
            ]
        );

        Auth::login($user, true);

        try {
            \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update([
                'last_ip' => $request->ip(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // don't break the login flow on IP save errors
        }

        return redirect()->intended('/');
    }

    // Redirect to authorize integrations (youtube/google or instagram/facebook)
    public function redirectIntegration(Request $request, $provider)
    {
        if (!in_array($provider, ['youtube', 'instagram', 'tiktok'])) {
            abort(404);
        }

        if ($provider === 'youtube') {
            // Request youtube upload and analytics scopes, offline access
            return Socialite::driver('google')
                ->scopes(['https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/yt-analytics.readonly'])
                ->with(['access_type' => 'offline', 'prompt' => 'consent'])
                ->stateless()
                ->redirect();
        }

        if ($provider === 'instagram') {
            // Use Facebook provider for Instagram Graph API scopes
            return Socialite::driver('facebook')
                ->scopes(['pages_show_list','pages_read_engagement','instagram_basic','instagram_content_publish'])
                ->stateless()
                ->redirect();
        }

        if ($provider === 'tiktok') {
            // TikTok OAuth (via SocialiteProviders/tiktok)
            return Socialite::driver('tiktok')
                ->stateless()
                ->redirect();
        }

        abort(400);
    }

    // Handle callback and store tokens in social_accounts table
    public function handleIntegrationCallback(Request $request, $provider)
    {
        if (!in_array($provider, ['youtube', 'instagram', 'tiktok'])) {
            abort(404);
        }

        $driver = null;
        if ($provider === 'youtube') {
            $driver = 'google';
        } elseif ($provider === 'instagram') {
            $driver = 'facebook';
        } elseif ($provider === 'tiktok') {
            $driver = 'tiktok';
        }

        $socialUser = Socialite::driver($driver)->stateless()->user();

        $user = Auth::user();
        if (! $user) {
            return redirect('/')->withErrors('You must be logged in to connect integrations.');
        }

        try {
            DB::table('social_accounts')->updateOrInsert(
                [
                    'user_id' => $user->id,
                    'provider' => $provider,
                ],
                [
                    'provider_user_id' => $socialUser->getId(),
                    'access_token' => $socialUser->token ?? null,
                    'refresh_token' => $socialUser->refreshToken ?? null,
                    'token_expires_at' => isset($socialUser->expiresIn) ? now()->addSeconds($socialUser->expiresIn) : null,
                    'scopes' => isset($socialUser->user['scope']) ? $socialUser->user['scope'] : null,
                    'meta' => json_encode($socialUser->user ?? []),
                    'revoked' => false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Integration callback store failed: '.$e->getMessage());
            return redirect()->route('integrations.index')->withErrors('Failed to store integration credentials.');
        }

        try {
            \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update([
                'last_ip' => $request->ip(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // ignore
        }

        return redirect()->route('integrations.index')->with('status', ucfirst($provider).' connected');
    }
}
