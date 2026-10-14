<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Account\AccountDeletionService;
use App\Services\Account\MeService;
use App\Services\Account\PreferencesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CONTRACT §10: Me, preferences, account deletion. */
class MeController extends Controller
{
    public function __construct(private MeService $me, private PreferencesService $preferences) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->me->present($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'min:1', 'max:100']]);

        $user = $request->user();
        $user->forceFill(['name' => trim($validated['name'])])->save();

        return response()->json($this->me->present($user));
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json($this->preferences->get($request->user()));
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        return response()->json($this->preferences->update($request->user(), $request->all()));
    }

    public function destroy(Request $request, AccountDeletionService $deletion): JsonResponse
    {
        $request->validate(['confirm' => ['required', 'string', 'in:DELETE']], [
            'confirm.in' => 'Type DELETE to confirm.',
        ]);

        $completesBy = $deletion->requestDeletion($request->user());

        return response()->json([
            'status' => 'scheduled',
            'completes_by' => $completesBy->toIso8601String(),
        ], 202);
    }
}
