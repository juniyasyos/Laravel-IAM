<?php

namespace App\Services\Sso;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\UserDataService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use JsonException;
use RuntimeException;

class TokenService
{
    public function __construct(
        private readonly SsoLogger $logger,
        private readonly UserDataService $userDataService
    ) {
    }

    /**
     * Issue a signed JWT for the given user and application.
     */
    public function issue(Authenticatable $user, Application $application): string
    {
        $trackingId = $this->logger->startPerformanceTracking('token_issue');

        try {
            $secret = $this->getSecret();

            $header = [
                'alg' => 'HS256',
                'typ' => 'JWT',
            ];

            $issuedAt = Carbon::now();
            $expiresAt = $issuedAt->clone()->addSeconds($application->getTokenExpirySeconds());

            // Get token payload with comprehensive user data
            $tokenPayload = $this->userDataService->getTokenPayload($user, $application);

            $payload = [
                'iss' => $this->getIssuer(),
                'sub' => (string) $user->getAuthIdentifier(),
                'email' => $user->email ?? null,
                'name' => $user->name ?? null,
                'app' => $application->app_key,
                'roles' => $tokenPayload['roles'] ?? [],
                'iat' => $issuedAt->getTimestamp(),
                'exp' => $expiresAt->getTimestamp(),
            ];

            $headerSegment = $this->base64UrlEncode($this->encodeJson($header));
            $payloadSegment = $this->base64UrlEncode($this->encodeJson($payload));

            $signature = hash_hmac('sha256', $headerSegment . '.' . $payloadSegment, $secret, true);
            $signatureSegment = $this->base64UrlEncode($signature);

            $token = implode('.', [$headerSegment, $payloadSegment, $signatureSegment]);
            $tokenPreview = substr($token, 0, 20) . '...';

            // Log token issuance
            $this->logger->logTokenIssued(
                userId: (int) $user->getAuthIdentifier(),
                appKey: $application->app_key,
                tokenPreview: $tokenPreview,
                ttl: $application->getTokenExpirySeconds(),
                additionalContext: [
                    'issuer' => $this->getIssuer(),
                    'token_length' => strlen($token),
                    'user_email' => $user->email ?? null,
                    'roles_count' => count($payload['roles']),
                ]
            );

            $this->logger->endPerformanceTracking($trackingId, [
                'app_key' => $application->app_key,
                'user_id' => $user->getAuthIdentifier(),
                'token_length' => strlen($token),
            ]);

            return $token;
        } catch (\Throwable $exception) {
            $this->logger->logException($exception, SsoLogger::CATEGORY_TOKEN_MGMT, [
                'operation' => 'token_issue',
                'user_id' => $user->getAuthIdentifier(),
                'app_key' => $application->app_key,
            ]);

            $this->logger->endPerformanceTracking($trackingId, [
                'operation_failed' => true,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Verify the incoming token and return its payload.
     *
     * @return array<string, mixed>
     */
    public function verify(string $token): array
    {
        $trackingId = $this->logger->startPerformanceTracking('token_verify');
        $tokenPreview = substr($token, 0, 20) . '...';

        try {
            $secret = $this->getSecret();

            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                $this->logger->logTokenVerificationFailed(
                    tokenPreview: $tokenPreview,
                    reason: 'Malformed token - incorrect number of segments',
                    additionalContext: [
                        'token_parts_count' => count($parts),
                        'expected_parts' => 3,
                    ]
                );
                throw new RuntimeException('Malformed SSO token.');
            }

            [$headerSegment, $payloadSegment, $signatureSegment] = $parts;

            $expectedSignature = hash_hmac('sha256', $headerSegment . '.' . $payloadSegment, $secret, true);
            $providedSignature = $this->base64UrlDecode($signatureSegment);

            if (! hash_equals($expectedSignature, $providedSignature)) {
                $this->logger->logTokenVerificationFailed(
                    tokenPreview: $tokenPreview,
                    reason: 'Invalid signature',
                    additionalContext: [
                        'signature_check_failed' => true,
                    ]
                );

                $this->logger->logSecurity('signature_verification_failed', [
                    'token_preview' => $tokenPreview,
                    'expected_signature_length' => strlen($expectedSignature),
                    'provided_signature_length' => strlen($providedSignature),
                ]);

                throw new RuntimeException('Invalid SSO token signature.');
            }

            $payload = $this->decodeJson($this->base64UrlDecode($payloadSegment));

            if (! is_array($payload)) {
                $this->logger->logTokenVerificationFailed(
                    tokenPreview: $tokenPreview,
                    reason: 'Invalid payload format',
                    additionalContext: [
                        'payload_type' => gettype($payload),
                    ]
                );
                throw new RuntimeException('Invalid SSO token payload.');
            }

            if (($payload['iss'] ?? null) !== $this->getIssuer()) {
                $this->logger->logTokenVerificationFailed(
                    tokenPreview: $tokenPreview,
                    reason: 'Invalid issuer',
                    additionalContext: [
                        'expected_issuer' => $this->getIssuer(),
                        'provided_issuer' => $payload['iss'] ?? null,
                    ]
                );

                $this->logger->logSecurity('invalid_issuer', [
                    'token_preview' => $tokenPreview,
                    'expected_issuer' => $this->getIssuer(),
                    'provided_issuer' => $payload['iss'] ?? null,
                ]);

                throw new RuntimeException('Invalid SSO issuer.');
            }

            $expiresAt = Carbon::createFromTimestamp($payload['exp'] ?? 0);

            if ($expiresAt->isPast()) {
                $this->logger->logTokenVerificationFailed(
                    tokenPreview: $tokenPreview,
                    reason: 'Token expired',
                    additionalContext: [
                        'expired_at' => $expiresAt->toIso8601String(),
                        'current_time' => Carbon::now()->toIso8601String(),
                        'expired_seconds_ago' => Carbon::now()->diffInSeconds($expiresAt),
                    ]
                );
                throw new RuntimeException('SSO token has expired.');
            }

            $applicationKey = $payload['app'] ?? null;

            if (! is_string($applicationKey) || empty($applicationKey)) {
                $this->logger->logTokenVerificationFailed(
                    tokenPreview: $tokenPreview,
                    reason: 'Missing or invalid application key',
                    additionalContext: [
                        'app_key_type' => gettype($applicationKey),
                        'app_key_empty' => empty($applicationKey),
                    ]
                );
                throw new RuntimeException('SSO token application is missing.');
            }

            $application = Application::enabled()
                ->where('app_key', $applicationKey)
                ->first();

            if ($application === null) {
                $this->logger->logTokenVerificationFailed(
                    tokenPreview: $tokenPreview,
                    reason: 'Application not found or disabled',
                    additionalContext: [
                        'app_key' => $applicationKey,
                    ]
                );

                $this->logger->logSecurity('invalid_application', [
                    'token_preview' => $tokenPreview,
                    'app_key' => $applicationKey,
                ]);

                throw new RuntimeException('SSO application is not available.');
            }

            // Log successful verification
            $this->logger->logTokenVerified(
                tokenPreview: $tokenPreview,
                payload: $payload,
                additionalContext: [
                    'application_id' => $application->id,
                    'application_name' => $application->name ?? 'N/A',
                ]
            );

            $this->logger->endPerformanceTracking($trackingId, [
                'token_length' => strlen($token),
                'app_key' => $applicationKey,
                'user_id' => $payload['sub'] ?? null,
                'verification_successful' => true,
            ]);

            return $payload;
        } catch (\Throwable $exception) {
            $this->logger->logException($exception, SsoLogger::CATEGORY_TOKEN_MGMT, [
                'operation' => 'token_verify',
                'token_preview' => $tokenPreview,
                'token_length' => strlen($token),
            ]);

            $this->logger->endPerformanceTracking($trackingId, [
                'operation_failed' => true,
                'error' => $exception->getMessage(),
                'token_preview' => $tokenPreview,
            ]);

            throw $exception;
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;

        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        if ($decoded === false) {
            $this->logger->logSecurity('base64_decode_failed', [
                'data_length' => strlen($data),
                'data_preview' => substr($data, 0, 20) . '...',
            ]);
            throw new RuntimeException('Failed decoding SSO token segment.');
        }

        return $decoded;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function encodeJson(array $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->logger->logException($exception, SsoLogger::CATEGORY_TOKEN_MGMT, [
                'operation' => 'json_encode',
                'data_keys' => array_keys($data),
            ]);
            throw new RuntimeException('Unable to encode SSO token segment.', 0, $exception);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $data): ?array
    {
        try {
            $decoded = json_decode($data, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->logger->logException($exception, SsoLogger::CATEGORY_TOKEN_MGMT, [
                'operation' => 'json_decode',
                'data_length' => strlen($data),
                'data_preview' => substr($data, 0, 50) . '...',
            ]);
            throw new RuntimeException('Unable to decode SSO token segment.', 0, $exception);
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function getSecret(): string
    {
        $secret = (string) Config::get('sso.secret');

        if (empty($secret)) {
            $this->logger->logSecurity('missing_sso_secret', [
                'config_key' => 'sso.secret',
                'app_env' => app()->environment(),
            ]);
            throw new RuntimeException('SSO secret is not configured.');
        }

        return $secret;
    }

    private function getIssuer(): string
    {
        $issuer = (string) Config::get('sso.issuer');

        return $issuer !== '' ? $issuer : 'iam-server';
    }

    private function getTtl(): int
    {
        $ttl = (int) Config::get('sso.ttl', 300);

        return $ttl > 0 ? $ttl : 300;
    }
}
