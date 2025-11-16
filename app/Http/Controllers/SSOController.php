<?php

namespace App\Http\Controllers;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\UserDataService;
use App\Models\User;
use App\Services\JWTTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SSOController extends Controller
{
    public function __construct(
        private JWTTokenService $jwtService,
        private UserDataService $userDataService
    ) {}

    /**
     * Step 1: Authorization endpoint
     * Aplikasi klien redirect user ke endpoint ini dengan app_key dan redirect_uri.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authorize(Request $request)
    {
        $request->validate([
            'app_key' => 'required|string',
            'redirect_uri' => 'required|url',
            'state' => 'nullable|string',
        ]);

        // Validasi aplikasi
        try {
            $application = Application::findByKey($request->app_key);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'invalid_client',
                'error_description' => 'Application not found',
            ], 404);
        }

        // Validasi enabled
        if (! $application->enabled) {
            return response()->json([
                'error' => 'unauthorized_client',
                'error_description' => 'Application is disabled',
            ], 403);
        }

        // Validasi redirect_uri
        if (! $application->isValidRedirectUri($request->redirect_uri)) {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'Invalid redirect URI',
            ], 400);
        }

        // Jika user belum login, redirect ke login dengan return URL
        if (! Auth::check()) {
            return redirect()->route('login')->with([
                'sso_return' => $request->fullUrl(),
            ]);
        }

        // Generate authorization code
        $authCode = Str::random(64);
        $codeData = [
            'user_id' => Auth::id(),
            'app_key' => $application->app_key,
            'redirect_uri' => $request->redirect_uri,
        ];

        // Store code in cache (5 minutes TTL)
        Cache::put("auth_code:{$authCode}", $codeData, 300);

        // Redirect ke aplikasi dengan code dan state
        $query = http_build_query([
            'code' => $authCode,
            'state' => $request->state,
        ]);

        return redirect($request->redirect_uri.'?'.$query);
    }

    /**
     * Step 2: Token endpoint
     * Aplikasi klien menukar authorization code dengan access token.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function token(Request $request): JsonResponse
    {
        $request->validate([
            'grant_type' => 'required|string|in:authorization_code,refresh_token',
            'app_key' => 'required|string',
            'app_secret' => 'required|string',
        ]);

        // Validasi aplikasi
        try {
            $application = Application::findByKey($request->app_key);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'invalid_client',
                'error_description' => 'Application not found',
            ], 404);
        }

        // Validasi app_secret
        if (! $application->verifySecret($request->app_secret)) {
            return response()->json([
                'error' => 'invalid_client',
                'error_description' => 'Invalid application credentials',
            ], 401);
        }

        if ($request->grant_type === 'authorization_code') {
            return $this->handleAuthorizationCodeGrant($request, $application);
        }

        if ($request->grant_type === 'refresh_token') {
            return $this->handleRefreshTokenGrant($request, $application);
        }

        return response()->json([
            'error' => 'unsupported_grant_type',
        ], 400);
    }

    /**
     * Handle authorization_code grant.
     *
     * @param  Request  $request
     * @param  Application  $application
     * @return JsonResponse
     */
    private function handleAuthorizationCodeGrant(Request $request, Application $application): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'redirect_uri' => 'required|url',
        ]);

        // Get code data from cache
        $codeData = Cache::get("auth_code:{$request->code}");

        if (! $codeData) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Authorization code is invalid or expired',
            ], 400);
        }

        // Validasi redirect_uri
        if ($codeData['redirect_uri'] !== $request->redirect_uri) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Redirect URI mismatch',
            ], 400);
        }

        // Validasi app_key
        if ($codeData['app_key'] !== $application->app_key) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Application mismatch',
            ], 400);
        }

        // Delete code (one-time use)
        Cache::forget("auth_code:{$request->code}");

        // Get user
        $user = User::findOrFail($codeData['user_id']);

        // Generate tokens
        $accessToken = $this->jwtService->generateAccessToken($user, $application);
        $refreshToken = $this->jwtService->generateRefreshToken($user, $application);

        // Get comprehensive user data
        $userData = $this->userDataService->getUserData($user, $application, true);

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $application->getTokenExpirySeconds(),
            'user' => $userData,
            'issued_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Handle refresh_token grant.
     *
     * @param  Request  $request
     * @param  Application  $application
     * @return JsonResponse
     */
    private function handleRefreshTokenGrant(Request $request, Application $application): JsonResponse
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        try {
            $decoded = $this->jwtService->verifyToken($request->refresh_token);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Invalid refresh token',
            ], 400);
        }

        // Validasi token type
        if (! isset($decoded->type) || $decoded->type !== 'refresh') {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Token is not a refresh token',
            ], 400);
        }

        // Validasi app_key
        if ($decoded->app_key !== $application->app_key) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Application mismatch',
            ], 400);
        }

        // Check if token is revoked
        if ($this->jwtService->isRefreshTokenRevoked($decoded)) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Refresh token has been revoked',
            ], 400);
        }

        // Get user
        $user = User::findOrFail($decoded->sub);

        // Generate new access token
        $accessToken = $this->jwtService->generateAccessToken($user, $application);

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $application->getTokenExpirySeconds(),
        ]);
    }

    /**
     * Revoke token endpoint.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function revoke(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'app_key' => 'required|string',
            'app_secret' => 'required|string',
        ]);

        // Validasi aplikasi
        try {
            $application = Application::findByKey($request->app_key);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'invalid_client',
            ], 404);
        }

        // Validasi app_secret
        if (! $application->verifySecret($request->app_secret)) {
            return response()->json([
                'error' => 'invalid_client',
            ], 401);
        }

        try {
            $decoded = $this->jwtService->verifyToken($request->token);

            if (isset($decoded->type) && $decoded->type === 'refresh') {
                $this->jwtService->revokeRefreshToken($decoded->sub, $decoded->app_key);
            }
        } catch (\Exception $e) {
            // Token sudah invalid, tidak perlu revoke
        }

        return response()->json([
            'message' => 'Token revoked successfully',
        ]);
    }

    /**
     * Introspect token endpoint - untuk validasi token dari aplikasi klien.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function introspect(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'app_key' => 'required|string',
            'app_secret' => 'required|string',
        ]);

        // Validasi aplikasi
        try {
            $application = Application::findByKey($request->app_key);
        } catch (\Exception $e) {
            return response()->json(['active' => false]);
        }

        // Validasi app_secret
        if (! $application->verifySecret($request->app_secret)) {
            return response()->json(['active' => false]);
        }

        try {
            $decoded = $this->jwtService->verifyToken($request->token);

            // Validasi app_key
            if (! isset($decoded->app_key) || $decoded->app_key !== $application->app_key) {
                return response()->json(['active' => false]);
            }

            // Get user for comprehensive data
            $user = User::find($decoded->sub);
            if (!$user) {
                return response()->json(['active' => false]);
            }

            $userData = $this->userDataService->getUserData($user, $application, false);

            return response()->json([
                'active' => true,
                'sub' => $decoded->sub,
                'name' => $decoded->name ?? null,
                'email' => $decoded->email ?? null,
                'roles' => $userData['application']['roles'] ?? [],
                'exp' => $decoded->exp,
                'iat' => $decoded->iat,
            ]);
        } catch (\Exception $e) {
            return response()->json(['active' => false]);
        }
    }

    /**
     * User info endpoint - mendapatkan informasi user dari access token.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function userInfo(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'Access token is required',
            ], 400);
        }

        try {
            $decoded = $this->jwtService->verifyToken($token);

            if (! isset($decoded->type) || $decoded->type !== 'access') {
                return response()->json([
                    'error' => 'invalid_token',
                    'error_description' => 'Token is not an access token',
                ], 401);
            }

            // Get user
            $user = User::findOrFail($decoded->sub);
            
            // Get application
            $application = Application::findByKey($decoded->app_key);

            // Get comprehensive user data
            $userData = $this->userDataService->getUserData($user, $application, true);

            return response()->json([
                'sub' => $decoded->sub,
                'user' => $userData,
                'token_info' => [
                    'issued_at' => $decoded->iat,
                    'expires_at' => $decoded->exp,
                    'app_key' => $decoded->app_key,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => $e->getMessage(),
            ], 401);
        }
    }
}
