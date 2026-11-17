<?php

namespace App\Studio\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Studio\Services\Usage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function show(Request $request, Usage $usage): JsonResponse
    {
        return response()->json($usage->present($request->user()));
    }
}
