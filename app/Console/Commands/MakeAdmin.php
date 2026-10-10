<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MakeAdmin extends Command
{
    protected $signature = 'app:make-admin {email} {--name=} {--password=}';

    protected $description = 'Create or promote a user to admin (can access the Filament panel).';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $password = $this->option('password') ?: Str::random(16);
            $user = User::create([
                'name' => $this->option('name') ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make($password),
                'is_admin' => true,
            ]);
            $this->info("Created admin user {$email}.");
            if (! $this->option('password')) {
                $this->warn("Generated password: {$password}");
            }

            return self::SUCCESS;
        }

        $user->forceFill(['is_admin' => true])->save();
        $this->info("Promoted {$email} to admin.");

        return self::SUCCESS;
    }
}
