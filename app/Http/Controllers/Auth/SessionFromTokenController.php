<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laravel\Passport\Token;
use App\Http\Controllers\Controller;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Symfony\Component\HttpFoundation\Cookie;

class SessionFromTokenController extends Controller
{
    /**
     * Exchange Passport Bearer token for Laravel session.
     * 
     * POST /auth/session-from-token
     * Body: { "access_token": "..." }
     * 
     * Returns 200 + sets Laravel session cookie if token valid.
     * This allows frontend (with Passport token) to auth with backend session.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->input('access_token');
        \Log::info('[SessionFromTokenController] START | Token provided: ' . (!empty($token) ? 'YES' : 'NO'));

        $request->validate([
            'access_token' => 'required|string',
        ]);

        try {
            \Log::debug('[SessionFromTokenController] Validating Passport token (JWT)...');

            // Extract JTI from JWT token
            // JWT format: header.payload.signature
            $tokenParts = explode('.', $token);
            if (count($tokenParts) !== 3) {
                \Log::warning('[SessionFromTokenController] Invalid token format');
                return response()->json([
                    'message' => 'Invalid token format',
                ], 401);
            }

            // Decode payload (no verification needed for JWT ID extraction)
            $payload = base64_decode(strtr($tokenParts[1], '-_', '+/'));
            $claims = json_decode($payload, true);

            if (!isset($claims['jti'])) {
                \Log::warning('[SessionFromTokenController] Token missing jti claim');
                return response()->json([
                    'message' => 'Invalid token structure',
                ], 401);
            }

            $jti = $claims['jti'];
            \Log::debug('[SessionFromTokenController] Token JTI: ' . $jti);

            $passportToken = Token::where('id', $jti)
                ->where('revoked', false)
                ->where('expires_at', '>', now())
                ->first();

            if (!$passportToken) {
                \Log::warning('[SessionFromTokenController] Token validation FAILED | Token not found or expired/revoked');
                return response()->json([
                    'message' => 'Invalid or expired token',
                ], 401);
            }

            \Log::debug('[SessionFromTokenController] Token validated | User ID: ' . $passportToken->user_id);

            $user = User::find($passportToken->user_id);

            if (!$user) {
                \Log::warning('[SessionFromTokenController] User not found for ID: ' . $passportToken->user_id);
                return response()->json([
                    'message' => 'User not found',
                ], 404);
            }

            // Authenticate user and login (sets Laravel session)
            \Log::debug('[SessionFromTokenController] Before login: auth()->id() = ' . (auth()->id() ?? 'NULL'));
            \Log::debug('[SessionFromTokenController] Session ID before: ' . session()->getId());

            // Start session if not already started
            if (!session()->isStarted()) {
                session()->start();
            }

            auth()->login($user);

            \Log::debug('[SessionFromTokenController] After login: auth()->id() = ' . (auth()->id() ?? 'NULL'));
            \Log::debug('[SessionFromTokenController] Session data after login: ' . json_encode(session()->all()));

            // Regenerate session ID for security (this is standard practice after login)
            session()->regenerate();

            \Log::debug('[SessionFromTokenController] After regenerate: Session ID = ' . session()->getId());

            // Force session to be saved/persisted to storage
            session()->save();

            \Log::debug('[SessionFromTokenController] After session()->save()');

            \Log::info('[SessionFromTokenController] User ' . $user->nip . ' authenticated from Passport token');
            \Log::debug('[SessionFromTokenController] Session ID: ' . session()->getId());
            \Log::debug('[SessionFromTokenController] Session auth: ' . auth()->id());

            // Check what's actually in the database
            $dbSession = \DB::table('sessions')->where('id', session()->getId())->first();
            \Log::debug('[SessionFromTokenController] DB session user_id: ' . ($dbSession ? $dbSession->user_id : 'NOT_FOUND'));

            $response = response()->json([
                'message' => 'Session created successfully',
                'user' => $user->only(['id', 'nip', 'name', 'email']),
                'session' => [
                    'id' => session()->getId(),
                    'name' => session()->getName(),
                ],
            ]);

            // Create session cookie with proper attributes from config
            // This ensures the cookie with same name and domain can be read by subsequent requests
            $sessCookie = cookie(
                name: session()->getName(),
                value: session()->getId(),
                minutes: config('session.lifetime'),
                path: config('session.path', '/'),
                domain: config('session.domain') ?: null,
                secure: config('session.secure', false),
                httpOnly: config('session.http_only', true),
                raw: false,
                sameSite: config('session.same_site', 'lax')
            );

            \Log::debug('[SessionFromTokenController] Cookie created | Name: ' . session()->getName() . ' | Domain: ' . (config('session.domain') ?: 'null') . ' | SameSite: ' . config('session.same_site', 'lax'));

            return $response->cookie($sessCookie);
        } catch (\Exception $e) {
            \Log::error('[SessionFromTokenController] Error creating session: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to create session',
            ], 500);
        }
    }
}
