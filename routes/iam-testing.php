<?php

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\UserDataService;
use App\Services\JWTTokenService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SSO Testing Routes
|--------------------------------------------------------------------------
|
| Testing routes for the new SSO system with comprehensive user data.
| These demonstrate token generation with roles, permissions, and access profiles.
|
*/

Route::prefix('sso-test')->group(function () {

    // Test 1: Generate JWT token for a user with specific application
    Route::get('/token/{email}/{appKey?}', function (string $email, ?string $appKey = null) {
        $user = \App\Models\User::where('email', $email)->firstOrFail();
        $jwtService = app(JWTTokenService::class);
        $userDataService = app(UserDataService::class);

        // Get application
        $application = $appKey 
            ? Application::where('app_key', $appKey)->where('enabled', true)->firstOrFail()
            : Application::where('enabled', true)->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'error' => 'No enabled application found',
            ], 404);
        }

        // Generate tokens
        $accessToken = $jwtService->generateAccessToken($user, $application);
        $refreshToken = $jwtService->generateRefreshToken($user, $application);

        // Decode and get payload
        $accessPayload = $jwtService->verifyToken($accessToken);
        $refreshPayload = $jwtService->verifyToken($refreshToken);

        // Get comprehensive user data
        $userData = $userDataService->getUserData($user, $application, true);

        return response()->json([
            'success' => true,
            'tokens' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $application->getTokenExpirySeconds(),
            ],
            'access_token_info' => [
                'length' => strlen($accessToken),
                'payload' => [
                    'iss' => $accessPayload->iss,
                    'sub' => $accessPayload->sub,
                    'iat' => $accessPayload->iat,
                    'exp' => $accessPayload->exp,
                    'name' => $accessPayload->name ?? null,
                    'email' => $accessPayload->email ?? null,
                    'app_key' => $accessPayload->app_key,
                    'roles' => $accessPayload->roles ?? [],
                    'type' => $accessPayload->type ?? 'access',
                ],
                'valid_for_app' => $accessPayload->app_key === $application->app_key,
                'expires_at' => date('Y-m-d H:i:s', $accessPayload->exp),
                'issued_at' => date('Y-m-d H:i:s', $accessPayload->iat),
            ],
            'refresh_token_info' => [
                'length' => strlen($refreshToken),
                'payload' => [
                    'iss' => $refreshPayload->iss,
                    'sub' => $refreshPayload->sub,
                    'iat' => $refreshPayload->iat,
                    'exp' => $refreshPayload->exp,
                    'app_key' => $refreshPayload->app_key,
                    'type' => $refreshPayload->type ?? 'refresh',
                ],
                'expires_at' => date('Y-m-d H:i:s', $refreshPayload->exp),
                'cached' => cache()->has("refresh_token:{$refreshPayload->sub}:{$refreshPayload->app_key}"),
            ],
            'user' => [
                'id' => $userData['id'],
                'name' => $userData['name'],
                'email' => $userData['email'],
                'unit' => $user->unit,
                'active' => $userData['active'],
                'roles' => $userData['application']['roles'] ?? [],
                'roles_count' => count($userData['application']['roles'] ?? []),
            ],
            'application' => [
                'app_key' => $application->app_key,
                'name' => $application->name,
                'enabled' => $application->enabled,
                'token_expiry' => $application->getTokenExpirySeconds(),
            ],
        ]);
    })->name('sso-test.token');

    // Test 2: View user's comprehensive data with roles and profiles
    Route::get('/user-data/{email}/{appKey?}', function (string $email, ?string $appKey = null) {
        $user = \App\Models\User::where('email', $email)->firstOrFail();
        $userDataService = app(UserDataService::class);

        // Get application if specified
        $application = $appKey 
            ? Application::where('app_key', $appKey)->where('enabled', true)->firstOrFail()
            : null;

        // Get comprehensive user data
        $userData = $userDataService->getUserData($user, $application, true);

        return response()->json([
            'success' => true,
            'user' => $userData,
            'summary' => [
                'total_apps' => $application ? 1 : count($userData['accessible_apps'] ?? []),
                'total_roles' => $application 
                    ? count($userData['application']['roles'] ?? [])
                    : collect($userData['applications'] ?? [])->sum(fn($app) => count($app['roles'])),
                'access_profiles_count' => count($userData['access_profiles'] ?? []),
                'direct_roles_count' => count($userData['direct_roles'] ?? []),
            ],
        ]);
    })->name('sso-test.user-data');

    // Test 3: List all applications with roles
    Route::get('/applications', function () {
        $applications = Application::with('roles')->where('enabled', true)->get();

        return response()->json([
            'success' => true,
            'applications' => $applications->map(function ($app) {
                return [
                    'app_key' => $app->app_key,
                    'name' => $app->name,
                    'description' => $app->description,
                    'enabled' => $app->enabled,
                    'token_expiry' => $app->getTokenExpirySeconds(),
                    'roles' => $app->roles->map(function ($role) {
                        return [
                            'id' => $role->id,
                            'slug' => $role->slug,
                            'name' => $role->name,
                            'description' => $role->description,
                            'is_system' => $role->is_system,
                            'users_count' => $role->users()->count(),
                        ];
                    }),
                    'stats' => [
                        'total_roles' => $app->roles->count(),
                        'system_roles' => $app->roles->where('is_system', true)->count(),
                        'custom_roles' => $app->roles->where('is_system', false)->count(),
                    ],
                ];
            }),
            'summary' => [
                'total_applications' => $applications->count(),
                'total_roles' => $applications->sum(fn($app) => $app->roles->count()),
            ],
        ]);
    })->name('sso-test.applications');

    // Test 4: Verify token endpoint
    Route::post('/verify', function () {
        $validated = request()->validate([
            'token' => 'required|string',
            'include_user_data' => 'boolean',
        ]);

        $jwtService = app(JWTTokenService::class);
        $userDataService = app(UserDataService::class);

        try {
            $payload = $jwtService->verifyToken($validated['token']);

            $response = [
                'success' => true,
                'valid' => true,
                'token_info' => [
                    'sub' => $payload->sub,
                    'app_key' => $payload->app_key,
                    'type' => $payload->type ?? 'access',
                    'issued_at' => date('Y-m-d H:i:s', $payload->iat),
                    'expires_at' => date('Y-m-d H:i:s', $payload->exp),
                    'is_expired' => $payload->exp < time(),
                ],
                'roles' => $payload->roles ?? [],
            ];

            if ($validated['include_user_data'] ?? false) {
                $user = \App\Models\User::find($payload->sub);
                if ($user) {
                    $application = Application::where('app_key', $payload->app_key)->first();
                    $response['user'] = $userDataService->getUserData($user, $application, true);
                }
            }

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'error' => $e->getMessage(),
            ], 401);
        }
    })->name('sso-test.verify');

    // Test 5: Access profiles management test
    Route::get('/access-profiles', function () {
        $profiles = \App\Domain\Iam\Models\AccessProfile::with('roles.application')->where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'access_profiles' => $profiles->map(function ($profile) {
                return [
                    'id' => $profile->id,
                    'slug' => $profile->slug,
                    'name' => $profile->name,
                    'description' => $profile->description,
                    'is_active' => $profile->is_active,
                    'is_system' => $profile->is_system,
                    'roles' => $profile->roles->map(function ($role) {
                        return [
                            'app_key' => $role->application->app_key,
                            'app_name' => $role->application->name,
                            'role_slug' => $role->slug,
                            'role_name' => $role->name,
                            'is_system' => $role->is_system,
                        ];
                    }),
                    'stats' => [
                        'roles_count' => $profile->roles->count(),
                        'users_count' => $profile->users()->count(),
                        'applications_count' => $profile->roles->pluck('application_id')->unique()->count(),
                    ],
                ];
            }),
            'summary' => [
                'total_profiles' => $profiles->count(),
                'system_profiles' => $profiles->where('is_system', true)->count(),
                'custom_profiles' => $profiles->where('is_system', false)->count(),
            ],
        ]);
    })->name('sso-test.access-profiles');
});
