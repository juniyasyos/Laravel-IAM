<?php

namespace App\Http\Middleware;

use App\Domain\Iam\Services\TokenBuilder;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySsoJwtApi
{
    public function __construct(
        private readonly TokenBuilder $tokenBuilder
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (empty($token)) {
            return response()->json([
                'message' => 'Unauthenticated: bearer token is required.',
            ], 401);
        }

        try {
            // Use TokenBuilder which uses Firebase\JWT - consistent with token issuance
            $claims = $this->tokenBuilder->verify($token);
            $payload = $claims->toPayload();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Unauthenticated: invalid or expired SSO token.',
            ], 401);
        }

        $userId = $payload['sub'] ?? null;
        if (empty($userId)) {
            return response()->json([
                'message' => 'Unauthenticated: invalid token subject.',
            ], 401);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated: user not found.',
            ], 401);
        }

        // Make Request::user() resolve to SSO-authenticated user.
        $request->setUserResolver(fn() => $user);
        $request->attributes->set('sso_payload', $payload);

        return $next($request);
    }
}
