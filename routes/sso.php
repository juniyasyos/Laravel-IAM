<?php

use App\Domain\Iam\Http\Controllers\SsoTokenController;
use App\Http\Controllers\SSOController;
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

        // OAuth2-like SSO Authorization Endpoint (new IAM)
        // Route::middleware('auth')->get('/oauth/authorize', [SsoTokenController::class, 'authorize'])
        //     ->name('oauth.authorize');

        Route::get('/sso/authorize', [\App\Http\Controllers\SSOController::class, 'authorize'])
            ->name('sso.authorize');

        // Front‑channel logout chain (public) — sequentially calls client `/iam/logout`
        Route::get('/sso/logout/chain', \App\Http\Controllers\Sso\SsoLogoutChainController::class)
            ->name('sso.logout.chain');
    },
    'api' => function (): void {
        Route::middleware(SsoLoggingMiddleware::class)
            ->group(function (): void {
                Route::post('/sso/verify', SsoVerifyController::class)
                    ->name('api.sso.verify');
            });

        // New IAM Token Endpoints
        Route::prefix('sso')->group(function () {
            Route::middleware('auth')->post('/token/issue', [SsoTokenController::class, 'issueToken'])
                ->name('sso.token.issue');

            Route::post('/token', [SsoTokenController::class, 'token'])
                ->name('sso.token.exchange');

            Route::post('/token/refresh', [SsoTokenController::class, 'refresh'])
                ->name('sso.token.refresh');

            Route::post('/introspect', [SsoTokenController::class, 'introspect'])
                ->name('sso.introspect');

            Route::get('/userinfo', [SsoTokenController::class, 'userinfo'])
                ->name('sso.userinfo');
        });

        // Legacy OAuth2 endpoints (keep for backward compatibility)
        Route::post('/oauth/token', [SSOController::class, 'token'])
            ->name('oauth.token');

        Route::post('/oauth/revoke', [SSOController::class, 'revoke'])
            ->name('oauth.revoke');

        Route::post('/oauth/introspect', [SSOController::class, 'introspect'])
            ->name('oauth.introspect');

        Route::get('/oauth/userinfo', [SSOController::class, 'userInfo'])
            ->name('api.oauth.userinfo');
    },
];
