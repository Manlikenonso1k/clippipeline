<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Services\ExpoPushService;
use Illuminate\Console\Command;

class PushBroadcast extends Command
{
    protected $signature = 'push:broadcast {title} {body} {--channel=sync}';

    protected $description = 'Send a push notification to every registered device (announcements, feature news)';

    public function handle(ExpoPushService $push): int
    {
        $tokens = DeviceToken::query()->pluck('token')->all();

        if (empty($tokens)) {
            $this->warn('No device tokens registered yet.');

            return self::FAILURE;
        }

        $push->send(
            $tokens,
            $this->argument('title'),
            $this->argument('body'),
            ['type' => 'broadcast'],
            (string) $this->option('channel'),
        );

        $this->info('Broadcast queued to '.count($tokens).' device(s).');

        return self::SUCCESS;
    }
}
