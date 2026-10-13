<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * Register (or refresh) an Expo push token for this device. Public route:
     * works signed-out (anonymous) and attaches to the user when a bearer token
     * is present, so the same device row is claimed on login.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:32'],
        ]);

        $user = $request->user() ?? auth('sanctum')->user();

        DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $user?->id,
                'platform' => $validated['platform'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return response()->json(['ok' => true]);
    }

    /** Remove a device token (e.g. on logout). */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        DeviceToken::where('token', $validated['token'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['ok' => true]);
    }
}
