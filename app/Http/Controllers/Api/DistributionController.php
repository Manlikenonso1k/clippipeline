<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostDistributionResource;
use App\Models\PostDistribution;
use App\Services\CrossPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DistributionController extends Controller
{
    /** POST /distributions/{id}/retry — owner-scoped, 409 unless failed. */
    public function retry(Request $request, string $distribution, CrossPostService $crossPost): JsonResponse
    {
        $model = PostDistribution::query()
            ->whereKey($distribution)
            ->whereHas('post', fn ($q) => $q->where('user_id', $request->user()->id))
            ->first();

        abort_unless($model, 404, 'Not found.');

        try {
            $retried = $crossPost->retry($model);
        } catch (ConflictHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json((new PostDistributionResource($retried))->resolve());
    }
}
