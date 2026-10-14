<?php

namespace App\Jobs;

use App\Models\DataDeletionRequest;
use App\Services\Compliance\MetaComplianceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Meta data-deletion callback purge (COMPLIANCE C-06). Idempotent. */
class PurgeMetaDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public string $metaUserId, public int $requestId) {}

    public function handle(MetaComplianceService $meta): void
    {
        $summary = $meta->purge($this->metaUserId);
        DataDeletionRequest::find($this->requestId)?->complete($summary);
    }
}
