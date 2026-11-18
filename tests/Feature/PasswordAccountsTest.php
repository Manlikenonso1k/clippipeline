<?php

namespace Tests\Feature;

use App\Filament\Pages\Profile;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_account_then_sign_in(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Ada', 'email' => 'Ada@Example.com', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']])->assertJsonPath('user.email', 'ada@example.com');
        $this->postJson('/api/auth/login', ['email' => 'ada@example.com', 'password' => 'password'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_create_account_validation_and_duplicates(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'short', 'password_confirmation' => 'short'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'password1', 'password_confirmation' => 'other123'])->assertStatus(422)->assertJsonValidationErrors('password');
        User::factory()->create(['email' => 'taken@example.com', 'password_is_random' => true]);
        $this->postJson('/api/auth/register', ['name' => 'B', 'email' => 'TAKEN@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_forgot_password_emails_a_website_link_and_hides_unknown_emails(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'nonso@example.com', 'password_is_random' => true]); // Google-made account
        $this->postJson('/api/auth/forgot-password', ['email' => 'nonso@example.com'])->assertOk();
        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, we have sent a link to reset the password. It expires in 60 minutes.');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $url = $n->toMail($user)->actionUrl;

            return str_contains($url, '/admin/password-reset/reset') && str_contains($url, 'token=');
        });
        Notification::assertCount(1);
    }

    public function test_reset_password_with_token(): void
    {
        $user = User::factory()->create(['email' => 'nonso@example.com']);
        $token = Password::createToken($user);
        $this->postJson('/api/auth/reset-password', ['token' => 'bad', 'email' => 'nonso@example.com', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertStatus(422);
        $this->postJson('/api/auth/reset-password', ['token' => $token, 'email' => 'nonso@example.com', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertOk();
        $this->assertTrue(Hash::check('newpass123', $user->refresh()->password));
    }

    public function test_change_password_needs_the_current_one_unless_there_is_none(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        Sanctum::actingAs($user);
        $this->getJson('/api/me')->assertJsonPath('user.has_password', true);
        $this->putJson('/api/me/password', ['current_password' => 'wrong', 'password' => 'n3w-secret', 'password_confirmation' => 'n3w-secret'])->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->putJson('/api/me/password', ['current_password' => 'password', 'password' => 'n3w-secret', 'password_confirmation' => 'n3w-secret'])->assertOk();
        $this->assertTrue(Hash::check('n3w-secret', $user->refresh()->password));

        $social = User::factory()->create(['google_id' => 'g-1', 'password_is_random' => true]);
        Sanctum::actingAs($social);
        $this->getJson('/api/me')->assertJsonPath('user.has_password', false);
        $this->putJson('/api/me/password', ['password' => 'first-pass', 'password_confirmation' => 'first-pass'])->assertOk();
        $this->assertTrue(Hash::check('first-pass', $social->refresh()->password));
        $this->assertFalse($social->password_is_random);
        $this->putJson('/api/me/password', ['password' => 'second-pass', 'password_confirmation' => 'second-pass'])->assertStatus(422)->assertJsonValidationErrors('current_password');
    }

    public function test_website_has_sign_up_forgot_password_and_profile_password(): void
    {
        $this->get('/admin/register')->assertOk();
        $this->get('/admin/password-reset/request')->assertOk();
        $this->get('/admin/login')->assertOk()->assertSee('/admin/register', false)->assertSee('/admin/password-reset/request', false);

        $user = User::factory()->create(['password' => Hash::make('password')]);
        $this->actingAs($user);
        Livewire::test(Profile::class)
            ->callAction('changePassword', ['current_password' => 'password', 'password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1'])
            ->assertHasNoActionErrors();
        $this->assertTrue(Hash::check('brand-new-1', $user->refresh()->password));
    }

    public function test_set_password_command_asks_hidden(): void
    {
        $this->artisan('app:set-password', ['email' => 'new@example.com', '--create' => true])
            ->expectsQuestion('New password (min 8 characters)', 'password')
            ->expectsQuestion('Repeat it', 'password')
            ->assertSuccessful();
        $this->assertTrue(Hash::check('password', User::where('email', 'new@example.com')->first()->password));
    }
}
