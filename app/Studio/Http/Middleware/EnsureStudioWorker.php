<?php

namespace App\Studio\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Worker endpoints need a Sanctum token that lists `studio:worker` explicitly. A normal
 * user token (abilities ["*"]) must not reach the worker API, so tokenCan() is not enough.
 */
class EnsureStudioWorker
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        // A wildcard token can() anything, including an ability that does not exist: reject it.
        $isWorker = $token && method_exists($token, 'can') && $token->can('studio:worker') && ! $token->can('studio:__wildcard_probe__');
        if (! $isWorker) {
            return response()->json(['message' => 'This endpoint is for the Studio render worker.'], 403);
        }

        return $next($request);
    }
}
