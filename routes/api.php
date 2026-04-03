<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SSOController;
use App\Http\Controllers\Auth\SessionFromTokenController;
use App\Http\Controllers\UserInfoController;
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

// Public auth routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// Exchange Passport token for Laravel web session (for frontend SSO)
// Not protected by auth:api because user doesn't have session yet
Route::post('/auth/session-from-token', SessionFromTokenController::class);

// Protected auth routes
Route::middleware('auth:api')->prefix('auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

// Protected user routes (use Passport auth:api, not internal SSO JWT)
Route::middleware('auth:api')->group(function () {
    Route::get('/users/me', UserInfoController::class);
    Route::get('/user', UserInfoController::class);
    Route::get('/users/applications', [UserInfoController::class, 'applications'])
        ->name('users.applications');
    Route::get('/users/applications/detail', [UserInfoController::class, 'applicationsDetail'])
        ->name('users.applications.detail');
});

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
