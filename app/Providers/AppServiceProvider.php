<?php

namespace App\Providers;

use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register SocialiteProviders manager so custom drivers (e.g., tiktok) are available
        $this->app->register(\SocialiteProviders\Manager\ServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Password-reset emails (app and website) link to the website's reset page.
        ResetPassword::createUrlUsing(fn ($user, string $token) => Filament::getPanel('admin')->getResetPasswordUrl($token, $user));
    }
}
