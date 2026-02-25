<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Passport::routes();

        Passport::useAccessTokenEntity(\App\Passport\AccessToken::class);

        // during testing, make tokens extremely short-lived so clients are
        // forced to re-authenticate quickly
        Passport::tokensExpireIn(now()->addSeconds(10));
        Passport::personalAccessTokensExpireIn(now()->addSeconds(10));
        Passport::refreshTokensExpireIn(now()->addHours(1));

        Log::info('AuthServiceProvider booted: dah habis dah habis login process dah habis');
    }
}
