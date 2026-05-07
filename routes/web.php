<?php

use App\Http\Controllers\UserInfoController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Root route - redirect based on auth status
Route::get('/', function () {
    return auth()->check()
        ? Redirect::to('/dashboard')
        : Redirect::to('/login');
})->name('home');

Route::get('/account-status', function (\Illuminate\Http\Request $request) {
    $user = $request->user();
    $reason = $request->session()->pull('inactive_reason');

    if (! $reason) {
        $reason = match ($user?->status) {
            'inactive' => 'Akun Anda sedang dinonaktifkan oleh administrator.',
            'suspended' => 'Akun Anda telah ditangguhkan oleh administrator.',
            default => 'Akun Anda tidak dapat mengakses sistem saat ini. Mohon hubungi administrator.',
        };
    }

    return Inertia::render('auth/Inactive', [
        'reason' => $reason,
        'status' => $user?->status,
    ]);
})->name('account.status');

Route::middleware(['web', 'auth', \App\Http\Middleware\BlockInactiveUser::class])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
        ->name('dashboard');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

$ssoRoutes = require __DIR__ . '/sso.php';

if (is_array($ssoRoutes) && isset($ssoRoutes['web']) && is_callable($ssoRoutes['web'])) {
    $ssoRoutes['web']();
}

if (app()->environment('testing')) {
    require __DIR__ . '/testing.php';
}
