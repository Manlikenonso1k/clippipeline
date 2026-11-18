<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Account\PasswordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/** Set (or reset) a user's password from the server, e.g. before email is configured. Asks for it hidden. */
class SetPassword extends Command
{
    protected $signature = 'app:set-password {email} {--create : Create the account if it does not exist} {--name=}';

    protected $description = 'Set a user\'s password (typed hidden, never shown or logged).';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user && ! $this->option('create')) {
            $this->error("No account for {$email}. Add --create to make one.");

            return self::FAILURE;
        }

        $password = (string) $this->secret('New password (min '.PasswordService::MIN_LENGTH.' characters)');
        if (mb_strlen($password) < PasswordService::MIN_LENGTH || $password !== (string) $this->secret('Repeat it')) {
            $this->error('Passwords must match and be at least '.PasswordService::MIN_LENGTH.' characters.');

            return self::FAILURE;
        }

        if (! $user) {
            $user = User::create(['name' => $this->option('name') ?: strtok($email, '@'), 'email' => $email, 'password' => Hash::make($password)]);
            $this->info("Created {$email}.");
        } else {
            $user->forceFill(['password' => Hash::make($password), 'password_is_random' => false])->save();
            $this->info("Password updated for {$email}.");
        }
        $this->line('Change it after you sign in: Profile → Password.');

        return self::SUCCESS;
    }
}
