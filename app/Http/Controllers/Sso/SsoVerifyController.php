<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Services\Sso\TokenService;
use App\Services\Sso\SsoLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SsoVerifyController extends Controller
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly SsoLogger $logger
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $trackingId = $this->logger->startPerformanceTracking('sso_verify');

        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $tokenPreview = substr($validated['token'], 0, 20) . '...';

        $this->logger->logWithRequest($request, SsoLogger::CATEGORY_TOKEN_MGMT, 'verify_request', [
            'token_preview' => $tokenPreview,
            'token_length' => strlen($validated['token']),
        ]);

        Log::info('[IAM] SSO: Verify request received', [
            'token_preview' => $tokenPreview,
            'token_length' => strlen($validated['token']),
        ]);

        try {
            $payload = $this->tokens->verify($validated['token']);

            Log::info('[IAM] SSO: Token verified successfully', [
                'user_id' => $payload['sub'] ?? null,
                'app' => $payload['app'] ?? null,
                'expires_at' => isset($payload['exp']) ? Carbon::createFromTimestamp($payload['exp'])->toIso8601String() : null,
            ]);

            $this->logger->endPerformanceTracking($trackingId, [
                'verification_successful' => true,
                'user_id' => $payload['sub'] ?? null,
                'app_key' => $payload['app'] ?? null,
                'token_length' => strlen($validated['token']),
            ]);

            return response()->json([
                // Backwards compatible: some clients expect `email` at the root level
                'email' => $payload['email'] ?? null,

                // Structured data also available under `user`
                'user' => [
                    'id' => $payload['sub'] ?? null,
                    'email' => $payload['email'] ?? null,
                ],
                'app' => $payload['app'] ?? null,
                'issuer' => $payload['iss'] ?? null,
                'expires_at' => isset($payload['exp']) ? Carbon::createFromTimestamp($payload['exp'])->toIso8601String() : null,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->logException($exception, SsoLogger::CATEGORY_TOKEN_MGMT, [
                'operation' => 'sso_verify',
                'token_preview' => $tokenPreview,
                'token_length' => strlen($validated['token']),
                'request_ip' => $request->ip(),
            ]);

            Log::error('[IAM] SSO: Token verification failed', [
                'error' => $exception->getMessage(),
                'token_preview' => $tokenPreview,
            ]);

            $this->logger->endPerformanceTracking($trackingId, [
                'operation_failed' => true,
                'error' => $exception->getMessage(),
                'token_preview' => $tokenPreview,
            ]);

            return response()->json([
                'message' => 'Invalid or expired token.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
