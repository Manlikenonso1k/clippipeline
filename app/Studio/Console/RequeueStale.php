<?php

namespace App\Studio\Console;

use App\Studio\Services\JobQueue;
use Illuminate\Console\Command;

class RequeueStale extends Command
{
    protected $signature = 'studio:requeue-stale';

    protected $description = 'Requeue Studio jobs whose worker lease expired (failed after max attempts).';

    public function handle(JobQueue $queue): int
    {
        $n = $queue->requeueStale();
        if ($n) {
            $this->info("Requeued or failed {$n} stale Studio job(s).");
        }

        return self::SUCCESS;
    }
}
