<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SocialAccountResource;
use App\Models\SocialAccount;
use App\Services\Connect\DisconnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialAccountController extends Controller
{
    /** GET /api/social-accounts — ordered youtube, facebook, instagram, tiktok. */
    public function index(Request $request): JsonResponse
    {
        $order = array_flip(SocialAccount::PLATFORMS);

        $accounts = SocialAccount::where('user_id', $request->user()->id)
            ->get()
            ->sortBy(fn (SocialAccount $a) => $order[$a->provider] ?? 99)
            ->values();

        return response()->json(SocialAccountResource::collection($accounts)->resolve($request));
    }

    /** DELETE /api/social-accounts/{id} — Meta rows (facebook + instagram) are removed together. */
    public function destroy(Request $request, string $id, DisconnectService $disconnect): JsonResponse
    {
        $ids = ctype_digit($id) ? $disconnect->disconnect($request->user(), (int) $id) : null;

        if ($ids === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['deleted_ids' => $ids]);
    }
}
