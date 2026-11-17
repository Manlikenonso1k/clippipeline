<?php

namespace App\Studio\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Studio\Models\StudioJob;
use App\Studio\Services\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobController extends Controller
{
    private function find(Request $request, string $id): StudioJob
    {
        $job = StudioJob::query()->where('user_id', $request->user()->id)->whereKey($id)->first();
        abort_unless($job, 404, 'Not found.');

        return $job;
    }

    public function show(Request $request, string $id, Presenter $present): JsonResponse
    {
        return response()->json($present->job($this->find($request, $id)));
    }

    public function cancel(Request $request, string $id, Presenter $present): JsonResponse
    {
        $job = $this->find($request, $id);
        if (! $job->isFinished()) {
            $job->forceFill(['status' => StudioJob::STATUS_CANCELED, 'finished_at' => now(), 'message' => 'Canceled', 'lease_expires_at' => null])->save();
        }

        return response()->json($present->job($job));
    }
}
