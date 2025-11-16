<?php

use App\Domain\Iam\Models\Application;
use App\Http\Controllers\Sso\SsoRedirectController;
use App\Services\JWTTokenService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Testing Routes
|--------------------------------------------------------------------------
|
| Routes for comprehensive testing of IAM SSO functionality during development
| These routes should be disabled in production
|
*/

// ============================================
// SSO Flow Testing
// ============================================

Route::get('/test-sso', function (Request $request) {
    $email = $request->get('user', 'doctor@gmail.com');
    $appKey = $request->get('app', 'siimut');

    // Auto-login specific user for testing
    $user = User::where('email', $email)->first();
    if ($user) {
        Auth::login($user);
    }

    // Redirect to OAuth authorize endpoint
    return redirect('/oauth/authorize?app_key='.$appKey.'&redirect_uri=http://127.0.0.1:8080/auth/callback&state='.Str::random(32));
})->name('test.sso');

Route::get('/test-sso-complete-flow', function (Request $request) {
    $email = $request->get('user', 'doctor@gmail.com');
    $appKey = $request->get('app', 'siimut');

    $user = User::where('email', $email)->first();
    $app = Application::where('app_key', $appKey)->first();

    if (!$user || !$app) {
        return response()->json([
            'error' => 'User or Application not found',
            'user_found' => !!$user,
            'app_found' => !!$app,
            'available_users' => User::pluck('email'),
            'available_apps' => Application::pluck('app_key'),
        ], 404);
    }

    // Simulate complete SSO flow
    $state = Str::random(40);
    $authCode = Str::random(64);

    // Store auth code (simulating OAuth authorize endpoint)
    Cache::put("auth_code:{$authCode}", [
        'user_id' => $user->id,
        'app_key' => $app->app_key,
        'redirect_uri' => 'http://127.0.0.1:8080/auth/callback',
    ], 300);

    // Exchange code for tokens (simulating token endpoint)
    $jwtService = new JWTTokenService();
    $accessToken = $jwtService->generateAccessToken($user, $app);
    $refreshToken = $jwtService->generateRefreshToken($user, $app);

    // Decode tokens to show payload
    $accessPayload = $jwtService->verifyToken($accessToken);
    $refreshPayload = $jwtService->verifyToken($refreshToken);

    return response()->json([
        'flow' => 'complete_sso_flow_simulation',
        'step_1_authorize' => [
            'state' => $state,
            'auth_code' => $authCode,
            'expires_in' => 300,
        ],
        'step_2_token_exchange' => [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $app->getTokenExpirySeconds(),
        ],
        'access_token_payload' => [
            'sub' => $accessPayload->sub,
            'name' => $accessPayload->name,
            'email' => $accessPayload->email,
            'roles' => $accessPayload->roles ?? [],
            'unit' => $user->unit,
            'app_key' => $accessPayload->app_key,
            'type' => $accessPayload->type,
            'exp' => date('Y-m-d H:i:s', $accessPayload->exp),
        ],
        'refresh_token_payload' => [
            'sub' => $refreshPayload->sub,
            'app_key' => $refreshPayload->app_key,
            'type' => $refreshPayload->type,
            'exp' => date('Y-m-d H:i:s', $refreshPayload->exp),
        ],
        'user_info' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'unit' => $user->unit,
            'roles' => $accessPayload->roles ?? [],
        ],
        'app_info' => [
            'app_key' => $app->app_key,
            'name' => $app->name,
            'token_expiry' => $app->token_expiry,
        ],
    ]);
})->name('test.sso.complete');

// ============================================
// Token Testing
// ============================================

Route::get('/test-token', function (Request $request) {
    $email = $request->get('user', 'doctor@gmail.com');
    $appKey = $request->get('app', 'siimut');

    $user = User::where('email', $email)->first();
    $app = Application::where('app_key', $appKey)->first();

    if (!$user || !$app) {
        return response()->json([
            'error' => 'User or Application not found',
            'user_found' => !!$user,
            'app_found' => !!$app,
            'hint' => 'Try ?user=admin@gmail.com&app=siimut',
        ], 404);
    }

    $jwtService = new JWTTokenService();

    try {
        // Generate both tokens
        $accessToken = $jwtService->generateAccessToken($user, $app);
        $refreshToken = $jwtService->generateRefreshToken($user, $app);

        // Verify tokens
        $accessPayload = $jwtService->verifyToken($accessToken);
        $refreshPayload = $jwtService->verifyToken($refreshToken);

        // Test token validation for specific app
        $isValidForApp = $jwtService->validateTokenForApp($accessToken, $app->app_key);

        return response()->json([
            'success' => true,
            'tokens' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $app->getTokenExpirySeconds(),
            ],
            'access_token_info' => [
                'length' => strlen($accessToken),
                'payload' => $accessPayload,
                'valid_for_app' => $isValidForApp,
                'expires_at' => date('Y-m-d H:i:s', $accessPayload->exp),
                'issued_at' => date('Y-m-d H:i:s', $accessPayload->iat),
            ],
            'refresh_token_info' => [
                'length' => strlen($refreshToken),
                'payload' => $refreshPayload,
                'expires_at' => date('Y-m-d H:i:s', $refreshPayload->exp),
                'cached' => Cache::has("refresh_token:{$user->id}:{$app->app_key}"),
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'unit' => $user->unit,
                'active' => $user->active,
                'roles' => $accessPayload->roles ?? [],
                'roles_count' => count($accessPayload->roles ?? []),
            ],
            'application' => [
                'app_key' => $app->app_key,
                'name' => $app->name,
                'enabled' => $app->enabled,
                'token_expiry' => $app->token_expiry,
            ],
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Token generation/verification failed',
            'message' => $e->getMessage(),
            'trace' => config('app.debug') ? $e->getTraceAsString() : null,
        ], 500);
    }
})->name('test.token');

Route::get('/test-token-refresh', function (Request $request) {
    $email = $request->get('user', 'doctor@gmail.com');
    $appKey = $request->get('app', 'siimut');

    $user = User::where('email', $email)->first();
    $app = Application::where('app_key', $appKey)->first();

    if (!$user || !$app) {
        return response()->json(['error' => 'User or Application not found'], 404);
    }

    $jwtService = new JWTTokenService();

    try {
        // Generate initial tokens
        $refreshToken = $jwtService->generateRefreshToken($user, $app);

        // Verify refresh token
        $refreshPayload = $jwtService->verifyToken($refreshToken);

        // Check if revoked
        $isRevoked = $jwtService->isRefreshTokenRevoked($refreshPayload);

        // Generate new access token using refresh token
        $newAccessToken = $jwtService->generateAccessToken($user, $app);

        return response()->json([
            'success' => true,
            'refresh_token_test' => [
                'refresh_token' => $refreshToken,
                'is_revoked' => $isRevoked,
                'can_generate_access_token' => !$isRevoked,
            ],
            'new_access_token' => $newAccessToken,
            'new_access_token_payload' => $jwtService->verifyToken($newAccessToken),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Token refresh test failed',
            'message' => $e->getMessage(),
        ], 500);
    }
})->name('test.token.refresh');

Route::get('/test-token-revoke', function (Request $request) {
    $email = $request->get('user', 'doctor@gmail.com');
    $appKey = $request->get('app', 'siimut');

    $user = User::where('email', $email)->first();
    $app = Application::where('app_key', $appKey)->first();

    if (!$user || !$app) {
        return response()->json(['error' => 'User or Application not found'], 404);
    }

    $jwtService = new JWTTokenService();

    try {
        // Generate refresh token
        $refreshToken = $jwtService->generateRefreshToken($user, $app);
        $payload = $jwtService->verifyToken($refreshToken);

        // Check before revocation
        $beforeRevoke = !$jwtService->isRefreshTokenRevoked($payload);

        // Revoke token
        $jwtService->revokeRefreshToken($user->id, $app->app_key);

        // Check after revocation
        $afterRevoke = $jwtService->isRefreshTokenRevoked($payload);

        return response()->json([
            'success' => true,
            'revocation_test' => [
                'refresh_token_generated' => substr($refreshToken, 0, 50).'...',
                'before_revoke_valid' => $beforeRevoke,
                'after_revoke_valid' => !$afterRevoke,
                'revocation_successful' => $beforeRevoke && $afterRevoke,
            ],
            'cache_key' => "refresh_token:{$user->id}:{$app->app_key}",
            'cache_exists_after_revoke' => Cache::has("refresh_token:{$user->id}:{$app->app_key}"),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Token revocation test failed',
            'message' => $e->getMessage(),
        ], 500);
    }
})->name('test.token.revoke');

// ============================================
// Permission & Role Testing
// ============================================

Route::get('/test-permissions', function (Request $request) {
    $email = $request->get('user', 'doctor@gmail.com');
    $user = User::where('email', $email)->first();

    if (!$user) {
        return response()->json([
            'error' => 'User not found',
            'available_users' => User::pluck('email'),
        ], 404);
    }

    $roles = $user->getRoleNames();
    $allPermissions = $user->getAllPermissions()->pluck('name');
    $directPermissions = $user->getDirectPermissions()->pluck('name');
    $permissionsViaRoles = $user->getPermissionsViaRoles()->pluck('name');

    // Test specific permissions
    $testPermissions = [
        'read:patients',
        'write:patients',
        'delete:patients',
        'manage:users',
        'create:prescriptions',
    ];

    $permissionTests = [];
    foreach ($testPermissions as $permission) {
        $permissionTests[$permission] = $user->hasPermissionTo($permission);
    }

    return response()->json([
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'unit' => $user->unit,
        ],
        'roles' => $roles,
        'permissions' => [
            'all' => $allPermissions,
            'direct' => $directPermissions,
            'via_roles' => $permissionsViaRoles,
            'total_count' => $allPermissions->count(),
        ],
        'permission_tests' => $permissionTests,
        'role_tests' => [
            'is_admin' => $user->hasRole('admin'),
            'is_doctor' => $user->hasRole('doctor'),
            'is_nurse' => $user->hasRole('nurse'),
            'has_any_medical_role' => $user->hasAnyRole(['doctor', 'nurse', 'pharmacist']),
        ],
    ]);
})->name('test.permissions');

Route::get('/test-all-roles', function () {
    $roles = \Spatie\Permission\Models\Role::with('permissions')->get();

    $roleData = $roles->map(function ($role) {
        return [
            'name' => $role->name,
            'permissions_count' => $role->permissions->count(),
            'permissions' => $role->permissions->pluck('name'),
            'users_count' => $role->users()->count(),
            'users' => $role->users()->pluck('email'),
        ];
    });

    return response()->json([
        'total_roles' => $roles->count(),
        'roles' => $roleData,
    ]);
})->name('test.roles');

// ============================================
// Application Testing
// ============================================

Route::get('/test-applications', function () {
    $apps = Application::all();

    $appData = $apps->map(function ($app) {
        return [
            'app_key' => $app->app_key,
            'name' => $app->name,
            'enabled' => $app->enabled,
            'redirect_uris' => $app->redirect_uris,
            'token_expiry' => $app->token_expiry,
            'secret_hash_preview' => substr($app->secret, 0, 16).'...',
            'creator' => $app->creator?->email,
        ];
    });

    return response()->json([
        'total_applications' => $apps->count(),
        'applications' => $appData,
    ]);
})->name('test.applications');

Route::get('/test-app-secret', function (Request $request) {
    $appKey = $request->get('app', 'siimut');
    $secret = $request->get('secret', 'siimut_secret_key_123');

    $app = Application::where('app_key', $appKey)->first();

    if (!$app) {
        return response()->json([
            'error' => 'Application not found',
            'available_apps' => Application::pluck('app_key'),
        ], 404);
    }

    $isValid = $app->verifySecret($secret);
    $isValidWrong = $app->verifySecret('wrong_secret');

    return response()->json([
        'app_key' => $app->app_key,
        'secret_verification' => [
            'correct_secret' => $isValid,
            'wrong_secret' => $isValidWrong,
            'hash_algorithm' => 'SHA-256',
            'stored_hash_preview' => substr($app->secret, 0, 16).'...',
        ],
        'redirect_uri_validation' => [
            'valid_uri' => $app->isValidRedirectUri('http://localhost:3000/auth/callback'),
            'invalid_uri' => $app->isValidRedirectUri('http://malicious.com/callback'),
            'registered_uris' => $app->redirect_uris,
        ],
    ]);
})->name('test.app.secret');

// ============================================
// Database Stats
// ============================================

Route::get('/test-stats', function () {
    return response()->json([
        'database_statistics' => [
            'users' => User::count(),
            'active_users' => User::where('active', true)->count(),
            'roles' => \Spatie\Permission\Models\Role::count(),
            'permissions' => \Spatie\Permission\Models\Permission::count(),
            'applications' => Application::count(),
            'enabled_applications' => Application::where('enabled', true)->count(),
        ],
        'users_by_role' => \Spatie\Permission\Models\Role::withCount('users')->get()->pluck('users_count', 'name'),
        'users_by_unit' => User::selectRaw('unit, count(*) as count')->groupBy('unit')->pluck('count', 'unit'),
        'cache_stats' => [
            'driver' => config('cache.default'),
            'sample_auth_codes' => Cache::get('auth_code:*') ? 'exists' : 'none',
        ],
    ]);
})->name('test.stats');

// ============================================
// Integration Test
// ============================================

Route::get('/test-integration', function (Request $request) {
    $results = [];

    // Test 1: User with roles and permissions
    $user = User::where('email', 'doctor@gmail.com')->first();
    $results['test_1_user_load'] = [
        'success' => !!$user,
        'has_roles' => $user ? $user->roles->count() > 0 : false,
        'has_permissions' => $user ? $user->getAllPermissions()->count() > 0 : false,
    ];

    // Test 2: Application with valid config
    $app = Application::where('app_key', 'siimut')->first();
    $results['test_2_app_load'] = [
        'success' => !!$app,
        'has_redirect_uris' => $app ? !empty($app->redirect_uris) : false,
        'has_secret' => $app ? !!$app->secret : false,
        'is_enabled' => $app ? $app->enabled : false,
    ];

    // Test 3: Token generation
    try {
        $jwtService = new JWTTokenService();
        $token = $jwtService->generateAccessToken($user, $app);
        $results['test_3_token_generation'] = [
            'success' => !!$token,
            'token_length' => strlen($token),
        ];

        // Test 4: Token verification
        $payload = $jwtService->verifyToken($token);
        $results['test_4_token_verification'] = [
            'success' => !!$payload,
            'has_required_claims' => isset($payload->sub, $payload->email, $payload->roles),
            'roles_count' => count($payload->roles ?? []),
        ];

        // Test 5: Refresh token
        $refreshToken = $jwtService->generateRefreshToken($user, $app);
        $results['test_5_refresh_token'] = [
            'success' => !!$refreshToken,
            'cached' => Cache::has("refresh_token:{$user->id}:{$app->app_key}"),
        ];

    } catch (\Exception $e) {
        $results['token_error'] = $e->getMessage();
    }

    // Overall success
    $allSuccess = collect($results)->every(function ($test) {
        return $test['success'] ?? false;
    });

    return response()->json([
        'integration_test_results' => $results,
        'all_tests_passed' => $allSuccess,
        'timestamp' => now()->toDateTimeString(),
    ]);
})->name('test.integration');
