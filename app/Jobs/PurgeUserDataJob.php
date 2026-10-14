<?php

namespace App\Jobs;

use App\Models\DataDeletionRequest;
use App\Models\User;
use App\Services\Account\AccountDeletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Queued account purge (COMPLIANCE A-02). Safe to run more than once. */
class PurgeUserDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $userId, public ?int $requestId = null) {}

    public function handle(AccountDeletionService $deletion): void
    {
        $user = User::find($this->userId);
        $summary = $user ? $deletion->purge($user) : [];

        if ($this->requestId && ($request = DataDeletionRequest::find($this->requestId))) {
            $request->complete($summary);
        }
    }
}
