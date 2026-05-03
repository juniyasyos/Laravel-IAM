<?php

use App\Http\Controllers\Api\SSOController;
use App\Http\Controllers\Api\TtdUrlController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Register general API routes here. SSO specific routes are configured in
| routes/sso.php.
|
*/

// Protected user data route for TTD pre-signed URLs.
Route::get('/users/{userId}/ttd-url', [TtdUrlController::class, 'show'])
    ->middleware(['sso.jwt'])
    ->whereNumber('userId');

// SSO Routes for Admin Panel access
Route::prefix('sso')->group(function () {
    Route::post('/admin/auth-code', [SSOController::class, 'generateAdminAuthCode']);
    Route::post('/admin/exchange-code', [SSOController::class, 'exchangeAdminAuthCode']);
    Route::post('/admin/verify-session', [SSOController::class, 'verifyAdminSession']);
});

$ssoRoutes = require __DIR__ . '/sso.php';

if (is_array($ssoRoutes) && isset($ssoRoutes['api']) && is_callable($ssoRoutes['api'])) {
    $ssoRoutes['api']();
}
