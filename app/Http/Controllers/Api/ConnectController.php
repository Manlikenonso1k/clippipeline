<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

// Stub created by lead; owning agent implements per docs/CONTRACT.md.
class ConnectController extends Controller
{
    public function start(): JsonResponse
    {
        return response()->json(['message' => 'Not implemented.'], 501);
    }
}
