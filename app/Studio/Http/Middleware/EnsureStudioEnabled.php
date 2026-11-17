<?php

namespace App\Studio\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudioEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('clippipeline.studio.enabled')) {
            return response()->json(['message' => 'Studio is not enabled on this server.'], 503);
        }

        return $next($request);
    }
}
