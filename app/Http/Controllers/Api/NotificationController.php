<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** CONTRACT §11 notification bell. */
class NotificationController extends Controller
{
    public function __construct(private NotificationFeed $feed) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return response()->json($this->feed->list($request->user(), (int) ($validated['limit'] ?? 30)));
    }

    public function readAll(Request $request): Response
    {
        $this->feed->markAllRead($request->user());

        return response()->noContent();
    }
}
