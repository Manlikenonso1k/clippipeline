<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Compliance\MetaComplianceService;
use App\Services\Compliance\MetaSignedRequest;
use App\Services\Compliance\TikTokWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Platform compliance callbacks (CONTRACT §15, COMPLIANCE C-06..C-08). */
class ComplianceController extends Controller
{
    public function metaDataDeletion(Request $request, MetaComplianceService $meta): JsonResponse
    {
        $payload = MetaSignedRequest::parse($request->input('signed_request'));
        $userId = $payload['user_id'] ?? null;
        if (! is_scalar($userId) || (string) $userId === '') {
            return response()->json(['message' => 'Invalid signed_request.'], 400);
        }

        $deletion = $meta->requestDeletion((string) $userId);

        return response()->json([
            'url' => url('/data-deletion/status').'?code='.$deletion->code,
            'confirmation_code' => $deletion->code,
        ]);
    }

    public function metaDeauthorize(Request $request, MetaComplianceService $meta): JsonResponse
    {
        $payload = MetaSignedRequest::parse($request->input('signed_request'));
        $userId = $payload['user_id'] ?? null;
        if (! is_scalar($userId) || (string) $userId === '') {
            return response()->json(['message' => 'Invalid signed_request.'], 400);
        }

        $meta->deauthorize((string) $userId);

        return response()->json(['ok' => true]);
    }

    public function tiktokWebhook(Request $request, TikTokWebhookService $tiktok): JsonResponse
    {
        $body = $request->getContent();
        if (! $tiktok->verify($request->header('TikTok-Signature'), $body)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = json_decode($body, true);
        if (is_array($event)) {
            $tiktok->handle($event);
        }

        return response()->json(['ok' => true]);
    }
}
