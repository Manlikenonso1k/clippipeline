<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ExpoPushService;
use Illuminate\Console\Command;

class PushTest extends Command
{
    protected $signature = 'push:test {email}';

    protected $description = 'Send a test push notification to a user by email';

    public function handle(ExpoPushService $push): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user found with that email.');

            return self::FAILURE;
        }

        $count = $user->deviceTokens()->count();

        if ($count === 0) {
            $this->warn("{$user->email} has no registered device tokens. Open the app (logged in) first.");

            return self::FAILURE;
        }

        $push->sendToUser(
            $user,
            'ClipPipeline',
            'Push notifications are working 🎉',
            ['type' => 'test'],
            'sync',
        );

        $this->info("Sent test push to {$count} device(s) for {$user->email}.");

        return self::SUCCESS;
    }
}
