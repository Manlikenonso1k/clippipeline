<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

// Stub created by lead; owning agent implements per docs/CONTRACT.md.
class SocialAccountController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['message' => 'Not implemented.'], 501);
    }

    public function destroy(): JsonResponse
    {
        return response()->json(['message' => 'Not implemented.'], 501);
    }
}
