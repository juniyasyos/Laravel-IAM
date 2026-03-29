<?php

use App\Http\Controllers\UserInfoController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Main welcome page with Blade
Route::get('/', [WelcomeController::class, 'index'])->name('home');

// Alternative Inertia welcome page (if needed)
Route::get('/vue-welcome', function () {
    return Inertia::render('Welcome');
})->name('vue.welcome');

// Vue dashboard routes are disabled - using Filament panel as main dashboard
// Route::redirect('dashboard', 'vue/dashboard')->middleware(['auth', 'verified']);
// Route::get('vue/dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

$ssoRoutes = require __DIR__ . '/sso.php';

if (is_array($ssoRoutes) && isset($ssoRoutes['web']) && is_callable($ssoRoutes['web'])) {
    $ssoRoutes['web']();
}

if (app()->environment('testing')) {
    require __DIR__ . '/testing.php';
}

Route::middleware('auth')
    ->get('/oauth/userinfo', UserInfoController::class)
    ->name('oauth.userinfo');

Route::middleware('auth')
    ->get('/iam/debug/user-applications', [UserInfoController::class, 'applications'])
    ->name('iam.debug.user-applications');

Route::middleware('auth')
    ->get('/iam/debug/user-access-profiles', [UserInfoController::class, 'accessProfiles'])
    ->name('iam.debug.user-access-profiles');
