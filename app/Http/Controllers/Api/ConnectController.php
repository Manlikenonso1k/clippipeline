<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Connect\ConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ConnectController extends Controller
{
    /** GET /api/connect/{provider}/start?platform=web|mobile (docs/CONTRACT.md §3). */
    public function start(Request $request, string $provider, ConnectService $connect): JsonResponse
    {
        $data = Validator::make(
            ['provider' => $provider, 'platform' => $request->query('platform', 'mobile')],
            [
                'provider' => ['required', Rule::in(ConnectService::PROVIDERS)],
                'platform' => ['required', Rule::in(ConnectService::PLATFORMS)],
            ],
        )->validate();

        return response()->json($connect->start($request->user(), $data['provider'], $data['platform']));
    }
}
