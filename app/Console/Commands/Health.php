<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Health extends Command
{
    protected $signature = 'app:health';

    protected $description = 'Report queue worker heartbeat, job counts, and connected social accounts.';

    public function handle(): int
    {
        $pending = $this->safeCount('jobs');
        $failed = $this->safeCount('failed_jobs');

        $heartbeat = cache()->get('queue:heartbeat');
        $alive = $heartbeat && now()->diffInMinutes($heartbeat) < 5;

        $this->info('Queue');
        $this->line('  worker alive: '.($alive ? 'yes (last beat '.$heartbeat.')' : 'NO heartbeat in last 5 min'));
        $this->line("  pending jobs: {$pending}");
        $this->line("  failed jobs:  {$failed}");

        $this->newLine();
        $this->info('Connected accounts');
        $accounts = SocialAccount::query()->get(['user_id', 'provider', 'handle', 'expires_at', 'needs_attention']);
        if ($accounts->isEmpty()) {
            $this->line('  (none)');
        }
        foreach ($accounts as $a) {
            $expiry = $a->expires_at ? $a->expires_at->toDateTimeString().($a->isExpired() ? ' (EXPIRED)' : '') : 'no expiry';
            $attn = $a->needs_attention ? ' [NEEDS ATTENTION]' : '';
            $this->line("  user {$a->user_id} · {$a->provider} · ".($a->handle ?: '—')." · {$expiry}{$attn}");
        }

        return self::SUCCESS;
    }

    private function safeCount(string $table): int
    {
        try {
            return DB::table($table)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
