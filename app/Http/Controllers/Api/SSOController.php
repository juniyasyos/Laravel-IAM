<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SSOController extends Controller
{
    /**
     * Generate authorization code untuk Admin Panel
     * Frontend call endpoint ini dengan access_token
     * Kemudian redirect ke Admin Panel dengan code ini
     */
    public function generateAdminAuthCode(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Generate authorization code valid untuk 5 menit
        $authCode = Str::random(64);
        Cache::put("admin_auth_code:{$authCode}", [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_nip' => $user->nip,
            'user_email' => $user->email,
        ], now()->addMinutes(5));

        return response()->json([
            'auth_code' => $authCode,
            'redirect_url' => env('ADMIN_PANEL_URL', 'http://localhost:8010') . '/auth/sso-callback',
        ]);
    }

    /**
     * Exchange authorization code untuk session di Admin Panel
     * Admin Panel call endpoint ini dengan code
     */
    public function exchangeAdminAuthCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $cacheKey = "admin_auth_code:{$request->code}";
        $userData = Cache::get($cacheKey);

        if (!$userData) {
            return response()->json([
                'message' => 'Invalid or expired authorization code',
            ], 401);
        }

        // Invalidate the code (one-time use)
        Cache::forget($cacheKey);

        // Generate session token yang bisa digunakan Admin Panel
        $sessionToken = Str::random(64);
        Cache::put("admin_session:{$sessionToken}", $userData, now()->addHours(8));

        return response()->json([
            'session_token' => $sessionToken,
            'user' => $userData,
            'expires_in' => 28800, // 8 hours in seconds
        ]);
    }

    /**
     * Verify session token (untuk Admin Panel validasi)
     */
    public function verifyAdminSession(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => 'required|string',
        ]);

        $userData = Cache::get("admin_session:{$request->session_token}");

        if (!$userData) {
            return response()->json([
                'message' => 'Invalid or expired session token',
            ], 401);
        }

        return response()->json([
            'user' => $userData,
            'valid' => true,
        ]);
    }
}
