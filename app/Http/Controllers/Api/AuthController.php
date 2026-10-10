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
