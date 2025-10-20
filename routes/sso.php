<?php

use App\Http\Controllers\Sso\SsoRedirectController;
use App\Http\Controllers\Sso\SsoVerifyController;
use App\Http\Middleware\SsoLoggingMiddleware;
use Illuminate\Support\Facades\Route;

return [
    'web' => function (): void {
        Route::middleware(['auth', SsoLoggingMiddleware::class])
            ->group(function (): void {
                Route::get('/sso/redirect', SsoRedirectController::class)
                    ->name('sso.redirect');
            });
    },
    'api' => function (): void {
        Route::middleware(SsoLoggingMiddleware::class)
            ->group(function (): void {
                Route::post('/sso/verify', SsoVerifyController::class)
                    ->name('api.sso.verify');
            });
    },
];
