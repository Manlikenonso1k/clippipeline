<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\LoginBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Mobile sign-in broker (CONTRACT §9). */
class AuthBrokerController extends Controller
{
    public function __construct(private LoginBroker $broker) {}

    public function start(Request $request, string $provider): JsonResponse
    {
        if (! in_array($provider, LoginBroker::PROVIDERS, true)) {
            return response()->json(['message' => 'Unknown provider.', 'errors' => ['provider' => ['Unknown provider.']]], 422);
        }

        $request->validate(['platform' => ['nullable', 'in:mobile']]);

        return response()->json($this->broker->start($provider));
    }

    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $this->broker->redeem($validated['code']);
        if (! $user) {
            throw ValidationException::withMessages(['code' => 'This sign-in code is invalid, expired or already used.']);
        }

        return response()->json([
            'token' => $user->createToken($validated['device_name'] ?? 'mobile')->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],
        ]);
    }
}
