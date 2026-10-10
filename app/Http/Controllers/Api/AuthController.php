<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function googleToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'redirect_uri' => ['required', 'url'],
        ]);

        $socialUser = Socialite::driver('google')
            ->stateless()
            ->redirectUrl($validated['redirect_uri'])
            ->userFromCode($validated['code']);

        $email = $socialUser->getEmail();
        if (! $socialUser->getId() || ! $email) {
            abort(422, 'Google did not return a usable account.');
        }

        $user = User::firstOrCreate(
            ['google_id' => $socialUser->getId()],
            [
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'Google User',
                'email' => $email,
                'avatar' => $socialUser->getAvatar(),
                'password' => Hash::make(Str::random(24)),
            ],
        );

        $user->fill([
            'name' => $socialUser->getName() ?? $user->name,
            'email' => $email,
            'avatar' => $socialUser->getAvatar() ?? $user->avatar,
        ])->save();

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! $user->password || ! Hash::check($validated['password'], $user->password)) {
            abort(422, 'These credentials do not match our records.');
        }

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function facebookToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'redirect_uri' => ['required', 'url'],
        ]);

        try {
            $socialUser = Socialite::driver('facebook')
                ->stateless()
                ->redirectUrl($validated['redirect_uri'])
                ->userFromCode($validated['code']);
        } catch (\Throwable $e) {
            abort(422, 'Unable to verify the Facebook account.');
        }

        if (! $socialUser->getId()) {
            abort(422, 'Facebook did not return a usable account.');
        }

        // Match by facebook_id first, then link to an existing account by email.
        $user = User::where('facebook_id', $socialUser->getId())->first();
        if (! $user && $socialUser->getEmail()) {
            $user = User::where('email', $socialUser->getEmail())->first();
        }

        if ($user) {
            $user->fill([
                'facebook_id' => $socialUser->getId(),
                'name' => $socialUser->getName() ?? $user->name,
                'avatar' => $socialUser->getAvatar() ?? $user->avatar,
            ])->save();
        } else {
            $user = User::create([
                'facebook_id' => $socialUser->getId(),
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'Facebook User',
                'email' => $socialUser->getEmail() ?: 'fb_'.$socialUser->getId().'@users.clippipeline.app',
                'avatar' => $socialUser->getAvatar(),
                'password' => Hash::make(Str::random(24)),
            ]);
        }

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function tiktokToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'redirect_uri' => ['required', 'url'],
        ]);

        try {
            $socialUser = Socialite::driver('tiktok')
                ->stateless()
                ->redirectUrl($validated['redirect_uri'])
                ->userFromCode($validated['code']);
        } catch (\Throwable $e) {
            abort(422, 'Unable to verify the TikTok account.');
        }

        if (! $socialUser->getId()) {
            abort(422, 'TikTok did not return a usable account.');
        }

        // TikTok does not return an email, so accounts are keyed on the TikTok id
        // and given a placeholder email on first login.
        $user = User::where('tiktok_id', $socialUser->getId())->first();

        if ($user) {
            $user->fill([
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? $user->name,
                'avatar' => $socialUser->getAvatar() ?? $user->avatar,
            ])->save();
        } else {
            $user = User::create([
                'tiktok_id' => $socialUser->getId(),
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'TikTok User',
                'email' => 'tiktok_'.$socialUser->getId().'@users.clippipeline.app',
                'avatar' => $socialUser->getAvatar(),
                'password' => Hash::make(Str::random(24)),
            ]);
        }

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function socialAccounts(Request $request): JsonResponse
    {
        return response()->json($request->user()->socialAccounts()->get([
            'id',
            'provider',
            'platform_name',
            'handle',
            'expires_at',
            'needs_attention',
            'meta',
        ]));
    }
}
