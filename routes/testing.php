<?php

use App\Http\Controllers\Sso\SsoRedirectController;
use App\Services\Sso\TokenService;
use App\Models\User;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Testing Routes
|--------------------------------------------------------------------------
|
| Routes for testing SSO functionality during development
|
*/

Route::get('/test-sso', function (Request $request) {
    // Auto-login as admin for testing
    $user = User::first();
    if ($user) {
        Auth::login($user);
    }

    // Redirect to SSO with client-example
    return redirect('/sso/redirect?app=client-example&callback=http://127.0.0.1:8080/auth/callback');
})->name('test.sso');

Route::get('/test-token', function (Request $request) {
    $user = User::first();
    $app = Application::where('app_key', 'client-example')->first();

    if (!$user || !$app) {
        return response()->json([
            'error' => 'User or Application not found',
            'user_found' => !!$user,
            'app_found' => !!$app,
        ], 404);
    }

    $tokenService = app(TokenService::class);

    try {
        // Generate token
        $token = $tokenService->issue($user, $app);

        // Verify token immediately
        $verified = false;
        $verifyError = null;
        try {
            $payload = $tokenService->verify($token);
            $verified = true;
        } catch (\Exception $e) {
            $verifyError = $e->getMessage();
        }

        return response()->json([
            'token' => $token,
            'token_length' => strlen($token),
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
            'app' => $app->app_key,
            'callback_url' => $app->callback_url,
            'verified' => $verified,
            'verify_error' => $verifyError,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Token generation failed',
            'message' => $e->getMessage(),
        ], 500);
    }
})->name('test.token');
