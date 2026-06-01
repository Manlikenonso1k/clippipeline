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
use Filament\Facades\Filament;

class SocialAuthController
{
    public function redirectToProvider(Request $request, $provider)
    {
        if ($provider !== 'google') {
            abort(404);
        }

        return Socialite::driver('google')->stateless()->redirect();
    }

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
                'password' => Hash::make(Str::random(24)),
            ]
        );

        Auth::login($user, true);

        // Bind Filament session explicitly
        try {
            Filament::auth()->login($user, remember: true);
        } catch (\Throwable $e) {
            // ignore if Filament not available
        }

        try {
            DB::table('users')->where('id', $user->id)->update([
                'last_ip' => $request->ip(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // don't break the login flow on IP save errors
        }

        return redirect()->to('/admin');
    }

    public function redirectIntegration(Request $request, $provider)
    {
        if (!in_array($provider, ['youtube', 'instagram', 'tiktok'])) {
            abort(404);
        }

        if ($provider === 'youtube') {
            return Socialite::driver('google')
                ->scopes(['https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/yt-analytics.readonly'])
                ->with(['access_type' => 'offline', 'prompt' => 'consent'])
                ->stateless()
                ->redirect();
        }

        if ($provider === 'instagram') {
            return Socialite::driver('facebook')
                ->scopes(['pages_show_list','pages_read_engagement','instagram_basic','instagram_content_publish'])
                ->stateless()
                ->redirect();
        }

        if ($provider === 'tiktok') {
            return Socialite::driver('tiktok')->stateless()->redirect();
        }

        abort(400);
    }

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
                    'access_token' => $socialUser->token ?? null,
                    'refresh_token' => $socialUser->refreshToken ?? null,
                    'expires_at' => isset($socialUser->expiresIn) ? now()->addSeconds($socialUser->expiresIn) : null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Integration callback store failed: '.$e->getMessage());
            return redirect()->route('filament.admin.pages.connect-accounts')->withErrors('Failed to store integration credentials.');
        }

        try {
            DB::table('users')->where('id', $user->id)->update([
                'last_ip' => $request->ip(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // ignore
        }

        return redirect()->route('filament.admin.pages.connect-accounts')->with('status', ucfirst($provider).' connected');
    }

    // Disconnect integration
    public function disconnect(Request $request, $provider)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('filament.admin.pages.connect-accounts');
        }

        DB::table('social_accounts')->where(['user_id' => $user->id, 'provider' => $provider])->delete();

        return redirect()->route('filament.admin.pages.connect-accounts')->with('status', ucfirst($provider).' disconnected');
    }
}
