<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SocialAccountResource;
use App\Models\SocialAccount;
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
    public function destroy(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->id;
        $account = ctype_digit($id) ? SocialAccount::where('user_id', $userId)->find((int) $id) : null;

        if (! $account) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $query = SocialAccount::where('user_id', $userId);
        $query = $account->connectProvider() === 'meta'
            ? $query->whereIn('provider', ['facebook', 'instagram'])
            : $query->whereKey($account->id);

        $ids = $query->orderBy('id')->pluck('id')->all();
        SocialAccount::whereIn('id', $ids)->delete();

        return response()->json(['deleted_ids' => $ids]);
    }
}
