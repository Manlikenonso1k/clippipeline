<?php

namespace App\Studio\Console;

use App\Studio\Services\ServiceUser;
use Illuminate\Console\Command;

class WorkerToken extends Command
{
    protected $signature = 'studio:worker-token {--name=render-worker : Token name} {--revoke : Revoke existing worker tokens first}';

    protected $description = 'Create a Sanctum token (ability studio:worker) for the Studio render worker.';

    public function handle(): int
    {
        $user = ServiceUser::get();
        if ($this->option('revoke')) {
            $count = $user->tokens()->delete();
            $this->line("Revoked {$count} old token(s).");
        }
        $token = $user->createToken((string) $this->option('name'), ['studio:worker']);

        $this->info('Put this in render-worker/.env as CLIPPIPELINE_WORKER_TOKEN (shown once):');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
