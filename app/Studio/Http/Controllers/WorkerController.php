<?php

namespace App\Studio\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Studio\Models\StudioJob;
use App\Studio\Services\JobCompletion;
use App\Studio\Services\JobQueue;
use App\Studio\Services\WorkerPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

/** Render worker API (CONTRACT §17.4). Token ability `studio:worker` only. */
class WorkerController extends Controller
{
    public function claim(Request $request, JobQueue $queue, WorkerPayload $payload): JsonResponse|Response
    {
        $v = $request->validate([
            'worker_id' => ['required', 'string', 'max:64'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string', 'max:40'],
        ]);
        Cache::put('studio:worker:last_seen', ['id' => $v['worker_id'], 'at' => now()->toIso8601String()], now()->addDay());

        $job = $queue->claim($v['worker_id'], $v['capabilities'] ?? []);

        return $job ? response()->json($payload->for($job)) : response()->noContent();
    }

    private function find(string $id, Request $request): StudioJob
    {
        $job = StudioJob::find($id);
        abort_unless($job, 404, 'Not found.');

        return $job;
    }

    public function heartbeat(Request $request, string $id, JobQueue $queue): JsonResponse
    {
        $v = $request->validate(['progress' => ['nullable', 'numeric', 'between:0,1'], 'message' => ['nullable', 'string', 'max:250']]);
        $job = $queue->heartbeat($this->find($id, $request), isset($v['progress']) ? (float) $v['progress'] : null, $v['message'] ?? null);

        // A canceled job tells the worker to stop.
        return response()->json(['status' => $job->status, 'lease_expires_at' => $job->lease_expires_at?->toIso8601String()]);
    }

    public function complete(Request $request, string $id, JobCompletion $completion): JsonResponse
    {
        $request->validate([
            'result' => ['nullable'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:'.((int) config('clippipeline.studio.max_upload_mb', 500) * 1024)],
        ]);
        $result = $request->input('result');
        if (is_string($result)) {
            $result = json_decode($result, true);
        }
        $files = array_filter((array) $request->file('files', []), fn ($f) => $f instanceof UploadedFile);

        $job = $completion->complete($this->find($id, $request), (array) ($result ?? []), $files);

        return response()->json(['status' => $job->status]);
    }

    public function fail(Request $request, string $id, JobCompletion $completion): JsonResponse
    {
        $v = $request->validate(['error' => ['required', 'string', 'max:5000'], 'retryable' => ['nullable', 'boolean']]);
        $job = $completion->fail($this->find($id, $request), $v['error'], (bool) ($v['retryable'] ?? false));

        return response()->json(['status' => $job->status]);
    }
}
