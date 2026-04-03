<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectToFrontend;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            if (file_exists(base_path('routes/testing.php'))) {
                Route::middleware('web')
                    ->group(base_path('routes/testing.php'));
            }

            if (file_exists(base_path('routes/iam-testing.php'))) {
                Route::middleware('api')
                    ->prefix('api')
                    ->group(base_path('routes/iam-testing.php'));
            }
        },
    )
    ->withProviders([
        App\Providers\AppServiceProvider::class,
        App\Providers\FortifyServiceProvider::class,
        App\Providers\PanelThemeServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(prepend: [
            \App\Http\Middleware\DebugRequestFlow::class,
        ], append: [
            \App\Http\Middleware\AuthenticateFromJWT::class,
            RedirectToFrontend::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Register IAM middleware aliases
        $middleware->alias([
            'iam.verify' => \App\Http\Middleware\VerifyIAMAccessToken::class,
            'iam.inject' => \App\Http\Middleware\InjectIAMUserContext::class,
            'iam.permission' => \App\Http\Middleware\CheckIAMPermission::class,
            'iam.role' => \App\Http\Middleware\CheckIAMRole::class,
            'sso.jwt' => \App\Http\Middleware\VerifySsoJwtApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
