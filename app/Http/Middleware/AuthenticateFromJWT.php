<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use Laravel\Passport\Token;

class AuthenticateFromJWT
{
    /**
     * Handle an incoming request.
     * 
     * If user is not authenticated via session, try to authenticate via Passport Bearer token.
     * This allows frontend SSO (OAuth2/Passport token in localStorage) to access backend panel.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If already authenticated via session, skip token check
        if (!auth()->check()) {
            \Log::debug('[AuthenticateFromJWT] User not authenticated, checking Passport token...');
            $this->authenticateFromPassportToken($request);
        } else {
            \Log::debug('[AuthenticateFromJWT] User already authenticated via session');
        }

        return $next($request);
    }

    protected function authenticateFromPassportToken(Request $request): void
    {
        $token = $this->extractBearerToken($request);

        if (!$token) {
            \Log::debug('[AuthenticateFromJWT] No Bearer token in Authorization header');
            return; // No token, let normal auth middleware handle it
        }

        \Log::debug('[AuthenticateFromJWT] Bearer token found, validating...');

        try {
            // Decode JWT to extract JTI claim
            // In Passport, the ID is stored as the 'jti' claim in the JWT payload
            $tokenParts = explode('.', $token);
            if (count($tokenParts) !== 3) {
                \Log::debug('[AuthenticateFromJWT] Invalid token format (not JWT)');
                return;
            }

            $payload = base64_decode(strtr($tokenParts[1], '-_', '+/'));
            $claims = json_decode($payload, true);

            if (!isset($claims['jti'])) {
                \Log::debug('[AuthenticateFromJWT] Token missing jti claim');
                return;
            }

            $jti = $claims['jti'];
            \Log::debug('[AuthenticateFromJWT] Token JTI: ' . $jti);

            // Check if token exists in oauth_access_tokens table
            // In Passport, the token ID is stored in the 'id' column
            $passportToken = Token::where('id', $jti)
                ->where('revoked', false)
                ->where('expires_at', '>', now())
                ->first();

            if ($passportToken) {
                $user = User::find($passportToken->user_id);
                if ($user) {
                    // Authenticate user by setting it in the 'api' guard (Passport)
                    // This bypasses session requirement
                    auth('api')->setUser($user);

                    \Log::info('[AuthenticateFromJWT] ✅ User authenticated from Passport token: ' . $user->nip);
                }
            } else {
                \Log::debug('[AuthenticateFromJWT] Token not found or revoked/expired');
            }
        } catch (\Exception $e) {
            \Log::warning('[AuthenticateFromJWT] Token validation error: ' . $e->getMessage());
        }
    }

    protected function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if ($header && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}
