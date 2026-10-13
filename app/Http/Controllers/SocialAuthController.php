<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connect\ConnectService;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    /** Web (session) entry: same flow as the API with platform=web. Accepts youtube|meta|instagram|tiktok. */
    public function redirectIntegration(Request $request, ConnectService $connect, $provider)
    {
        $provider = ConnectService::normalize((string) $provider);
        if (! $provider) {
            abort(404);
        }

        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        return redirect()->away($connect->start($user, $provider, 'web')['auth_url']);
    }

    /**
     * OAuth callback for youtube|instagram(meta)|tiktok. Runs without a session
     * (mobile): the user comes from the signed state, the session only for legacy links.
     */
    public function handleIntegrationCallback(Request $request, ConnectService $connect, $provider)
    {
        if (! array_key_exists($provider, ConnectService::CALLBACK_SEGMENTS)) {
            abort(404);
        }

        $result = $connect->handleCallback($request, $provider);

        if ($result['platform'] === 'mobile') {
            $query = ['provider' => $result['provider'], 'status' => $result['ok'] ? 'ok' : 'error'];
            if (! $result['ok']) {
                $query['message'] = $result['message'];
            }

            $base = (string) config('services.mobile.connect_redirect');

            return redirect()->away($base.(str_contains($base, '?') ? '&' : '?').http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        }

        $redirect = redirect()->route('filament.admin.pages.connect-accounts');

        return $result['ok']
            ? $redirect->with('status', $result['message'])
            : $redirect->withErrors($result['message']);
    }

    // Disconnect integration (web). Meta rows share one token, so both go together.
    public function disconnect(Request $request, $provider)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('filament.admin.pages.connect-accounts');
        }

        $normalized = ConnectService::normalize((string) $provider);
        $providers = $normalized === 'meta' ? ['facebook', 'instagram'] : [(string) $provider];

        SocialAccount::where('user_id', $user->id)->whereIn('provider', $providers)->delete();

        return redirect()->route('filament.admin.pages.connect-accounts')
            ->with('status', ConnectService::label($normalized ?? (string) $provider).' disconnected');
    }
}
