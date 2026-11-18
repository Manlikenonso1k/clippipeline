<?php

namespace App\Services\Account;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Email/password accounts (CONTRACT §18): change password, shared by the API and the web profile page. */
class PasswordService
{
    public const MIN_LENGTH = 8;

    /** Accounts created with Google/Facebook/TikTok have no password yet: they may set one without the old one. */
    public function hasPassword(User $user): bool
    {
        return (string) $user->password !== '' && ! $user->password_is_random;
    }

    /** @throws ValidationException */
    public function change(User $user, ?string $current, string $new): void
    {
        if ($this->hasPassword($user) && ! Hash::check((string) $current, (string) $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is not correct.']);
        }
        if (mb_strlen($new) < self::MIN_LENGTH) {
            throw ValidationException::withMessages(['password' => 'Use at least '.self::MIN_LENGTH.' characters.']);
        }
        $user->forceFill(['password' => Hash::make($new), 'password_is_random' => false])->save();
    }
}
