<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

// Stub created by lead; owning agent implements per docs/CONTRACT.md.
class DistributionController extends Controller
{
    public function retry(): JsonResponse
    {
        return response()->json(['message' => 'Not implemented.'], 501);
    }
}
