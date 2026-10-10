<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\InstagramService;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

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
        if (! in_array($provider, ['youtube', 'instagram', 'tiktok'])) {
            abort(404);
        }

        if ($provider === 'youtube') {
            return Socialite::driver('google')
                ->scopes(['https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/yt-analytics.readonly'])
                ->with(['access_type' => 'offline', 'prompt' => 'consent'])
                ->redirectUrl(route('integrations.callback', ['provider' => 'youtube']))
                ->stateless()
                ->redirect();
        }

        if ($provider === 'instagram') {
            return Socialite::driver('facebook')
                ->scopes(['pages_show_list', 'pages_read_engagement', 'instagram_basic', 'instagram_content_publish'])
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
        if (! in_array($provider, ['youtube', 'instagram', 'tiktok'])) {
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

        $socialite = Socialite::driver($driver)->stateless();

        // Google validates that the token-exchange redirect_uri matches the one used
        // to start the flow, so re-apply the same integrations callback here.
        if ($provider === 'youtube') {
            $socialite = $socialite->redirectUrl(route('integrations.callback', ['provider' => 'youtube']));
        }

        $socialUser = $socialite->user();

        $user = Auth::user();
        if (! $user) {
            return redirect('/')->withErrors('You must be logged in to connect integrations.');
        }

        // For Instagram, resolve the business user id now (needed to publish Reels).
        $meta = [];
        $needsAttention = false;
        if ($provider === 'instagram' && ($socialUser->token ?? null)) {
            $resolved = app(InstagramService::class)->resolveBusinessUserId($socialUser->token);
            if ($resolved) {
                $meta['ig_user_id'] = $resolved['ig_user_id'];
                $meta['login_flow'] = $resolved['flow'];
            } else {
                $needsAttention = true;
            }
        }

        try {
            DB::table('social_accounts')->updateOrInsert(
                [
                    'user_id' => $user->id,
                    'provider' => $provider,
                ],
                [
                    // platform_name has its own unique(user_id, platform_name) index and
                    // defaults to 'tiktok'; set it to the real provider or the second
                    // platform you connect collides with the first and the insert fails.
                    'platform_name' => $provider,
                    'access_token' => $socialUser->token ?? null,
                    'refresh_token' => $socialUser->refreshToken ?? null,
                    'expires_at' => isset($socialUser->expiresIn) ? now()->addSeconds($socialUser->expiresIn) : null,
                    'meta' => $meta ? json_encode($meta) : null,
                    'needs_attention' => $needsAttention,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Integration callback store failed: '.$e->getMessage());

            return redirect()->route('filament.admin.pages.connect-accounts')->withErrors('Failed to store integration credentials.');
        }

        if ($needsAttention) {
            return redirect()->route('filament.admin.pages.connect-accounts')
                ->withErrors('Instagram connected, but no Business/Creator account was found. Link an Instagram Business account to a Facebook Page, then use Reconnect.');
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
