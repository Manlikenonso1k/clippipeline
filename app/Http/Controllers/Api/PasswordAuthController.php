<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Account\PasswordService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Create account, forgot / reset password, change password (CONTRACT §18). */
class PasswordAuthController extends Controller
{
    /** POST /auth/register → {token, user} (same shape as POST /auth/login). */
    public function register(Request $request): JsonResponse
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:'.PasswordService::MIN_LENGTH, 'max:255', 'confirmed'],
        ]);
        $email = Str::lower(trim($v['email']));
        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'An account with this email already exists. Sign in instead — or tap "Forgot password?" to set a password (accounts made with Google, Facebook or TikTok start without one).']);
        }

        $user = User::create(['name' => trim($v['name']), 'email' => $email, 'password' => Hash::make($v['password'])]);

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'avatar' => $user->avatar],
        ], 201);
    }

    /**
     * POST /auth/forgot-password → always 200 with the same message, so the endpoint cannot be used to
     * find out which emails have accounts. The email links to the website's reset page.
     */
    public function forgot(Request $request): JsonResponse
    {
        $v = $request->validate(['email' => ['required', 'email', 'max:255']]);
        Password::sendResetLink(['email' => Str::lower(trim($v['email']))]);

        return response()->json(['message' => 'If an account exists for that email, we have sent a link to reset the password. It expires in 60 minutes.']);
    }

    /** POST /auth/reset-password {token, email, password, password_confirmation}. */
    public function reset(Request $request): JsonResponse
    {
        $v = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:'.PasswordService::MIN_LENGTH, 'max:255', 'confirmed'],
        ]);
        $status = Password::reset(
            ['email' => Str::lower(trim($v['email'])), 'password' => $v['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $v['token']],
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password), 'password_is_random' => false, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            },
        );
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => 'This reset link is invalid or has expired. Ask for a new one.']);
        }

        return response()->json(['message' => 'Your password has been changed. You can sign in now.']);
    }

    /** PUT /me/password {current_password?, password, password_confirmation}. */
    public function change(Request $request, PasswordService $passwords): JsonResponse
    {
        $v = $request->validate([
            'current_password' => ['nullable', 'string'],
            'password' => ['required', 'string', 'min:'.PasswordService::MIN_LENGTH, 'max:255', 'confirmed'],
        ]);
        $passwords->change($request->user(), $v['current_password'] ?? null, $v['password']);

        return response()->json(['message' => 'Password updated.', 'has_password' => true]);
    }
}
