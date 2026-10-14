<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TikTok\CreatorInfoService;
use App\Services\TikTok\NoTikTokAccountException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CONTRACT §13 creator info (T-01). */
class TikTokController extends Controller
{
    public function creatorInfo(Request $request, CreatorInfoService $creatorInfo): JsonResponse
    {
        try {
            return response()->json($creatorInfo->forUser($request->user()));
        } catch (NoTikTokAccountException) {
            return response()->json(['message' => 'Not found.'], 404);
        }
    }
}
