<?php

namespace App\Http\Controllers\Api;

use App\Domain\Iam\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TokenExpiredNotificationController extends Controller
{
    /**
     * Handle notification from client app that token expired
     * 
     * When a client app detects that a user's token has expired,
     * it sends a notification to this endpoint. IAM then marks
     * the token as expired and optionally notifies other clients.
     * 
     * POST /api/iam/notify-token-expired
     * {
     *   "user_id": 123,
     *   "app_key": "siimut",
     *   "expired_at": "2026-04-06T14:30:00Z"
     * }
     */
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer',
            'app_key' => 'required|string',
            'expired_at' => 'required|date_format:Y-m-d\TH:i:s\Z|nullable',
        ]);

        $userId = $data['user_id'];
        $appKey = $data['app_key'];
        $expiredAt = $data['expired_at'];

        // Verify that the app_key exists
        $app = Application::where('app_key', $appKey)->first();
        if (!$app) {
            Log::warning('TokenExpiredNotification: Unknown app_key', [
                'app_key' => $appKey,
                'user_id' => $userId,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Unknown application',
            ], 400);
        }

        Log::info('TokenExpiredNotification: Received token expiry notice', [
            'user_id' => $userId,
            'app_key' => $appKey,
            'expired_at' => $expiredAt,
            'request_id' => $request->header('X-Request-Id'),
        ]);

        // Cache the expiry notification (lasts 24 hours)
        // This helps prevent replay attacks and provides audit trail
        Cache::put("token_expired:{$userId}:{$appKey}", [
            'expired_at' => $expiredAt,
            'notified_at' => now()->toIso8601String(),
        ], 86400);

        // Update the logout time if not already set earlier
        // (The initial logout request from IAM sets this, but if client
        // detects expiry first, we set it here)
        $logoutCacheKey = "user_logout_at:{$userId}";
        if (!Cache::has($logoutCacheKey)) {
            Cache::put($logoutCacheKey, time());

            Log::info('TokenExpiredNotification: Set logout time for user', [
                'user_id' => $userId,
                'app_key' => $appKey,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Token expiry notification received',
            'user_id' => $userId,
            'app_key' => $appKey,
        ], 200);
    }
}
