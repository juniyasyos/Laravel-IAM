<?php

namespace App\Services\Sso;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * SSO Logger Service
 *
 * Sistem logging khusus untuk SSO dengan 4 kriteria utama:
 * 1. Authentication Flow - Track alur autentikasi dari awal hingga akhir
 * 2. Token Management - Monitor pembuatan, verifikasi, dan validasi token
 * 3. Security Events - Catat semua event keamanan dan anomali
 * 4. Performance Metrics - Ukur performa dan response time
 */
class SsoLogger
{
    // Konstanta untuk level logging
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';
    public const LEVEL_DEBUG = 'debug';

    // Konstanta untuk kategori logging (4 kriteria utama)
    public const CATEGORY_AUTH_FLOW = 'auth_flow';
    public const CATEGORY_TOKEN_MGMT = 'token_management';
    public const CATEGORY_SECURITY = 'security_events';
    public const CATEGORY_PERFORMANCE = 'performance_metrics';

    // Konstanta untuk event types
    public const EVENT_LOGIN_ATTEMPT = 'login_attempt';
    public const EVENT_LOGIN_SUCCESS = 'login_success';
    public const EVENT_LOGIN_FAILED = 'login_failed';
    public const EVENT_SSO_REDIRECT = 'sso_redirect';
    public const EVENT_SSO_CALLBACK = 'sso_callback';
    public const EVENT_TOKEN_ISSUED = 'token_issued';
    public const EVENT_TOKEN_VERIFIED = 'token_verified';
    public const EVENT_TOKEN_EXPIRED = 'token_expired';
    public const EVENT_TOKEN_INVALID = 'token_invalid';
    public const EVENT_SECURITY_VIOLATION = 'security_violation';
    public const EVENT_RATE_LIMIT = 'rate_limit';
    public const EVENT_PERFORMANCE_SLOW = 'performance_slow';

    private array $sessionContext = [];

    /**
     * Log Authentication Flow Events
     */
    public function logAuthFlow(string $event, array $context = [], string $level = self::LEVEL_INFO): void
    {
        $this->log(self::CATEGORY_AUTH_FLOW, $event, $context, $level);
    }

    /**
     * Log Token Management Events
     */
    public function logTokenManagement(string $event, array $context = [], string $level = self::LEVEL_INFO): void
    {
        $this->log(self::CATEGORY_TOKEN_MGMT, $event, $context, $level);
    }

    /**
     * Log Security Events
     */
    public function logSecurity(string $event, array $context = [], string $level = self::LEVEL_WARNING): void
    {
        $this->log(self::CATEGORY_SECURITY, $event, $context, $level);
    }

    /**
     * Log Performance Metrics
     */
    public function logPerformance(string $event, array $context = [], string $level = self::LEVEL_INFO): void
    {
        $this->log(self::CATEGORY_PERFORMANCE, $event, $context, $level);
    }

    /**
     * Start performance tracking
     */
    public function startPerformanceTracking(string $operation): string
    {
        $trackingId = uniqid('perf_');
        $this->sessionContext[$trackingId] = [
            'operation' => $operation,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
        ];
        return $trackingId;
    }

    /**
     * End performance tracking and log results
     */
    public function endPerformanceTracking(string $trackingId, array $additionalContext = []): void
    {
        if (!isset($this->sessionContext[$trackingId])) {
            return;
        }

        $tracking = $this->sessionContext[$trackingId];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $duration = round(($endTime - $tracking['start_time']) * 1000, 2); // in milliseconds
        $memoryUsed = $endMemory - $tracking['start_memory'];

        $context = array_merge([
            'operation' => $tracking['operation'],
            'duration_ms' => $duration,
            'memory_used_bytes' => $memoryUsed,
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
        ], $additionalContext);

        // Log sebagai slow performance jika durasi > 2 detik
        $level = $duration > 2000 ? self::LEVEL_WARNING : self::LEVEL_INFO;
        $event = $duration > 2000 ? self::EVENT_PERFORMANCE_SLOW : 'performance_tracked';

        $this->logPerformance($event, $context, $level);

        unset($this->sessionContext[$trackingId]);
    }

    /**
     * Log exception with context
     */
    public function logException(Throwable $exception, string $category, array $context = []): void
    {
        $exceptionContext = [
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'exception_trace' => $exception->getTraceAsString(),
        ];

        $this->log($category, 'exception', array_merge($context, $exceptionContext), self::LEVEL_ERROR);
    }

    /**
     * Log dengan request context otomatis
     */
    public function logWithRequest(Request $request, string $category, string $event, array $context = [], string $level = self::LEVEL_INFO): void
    {
        $requestContext = $this->buildRequestContext($request);
        $this->log($category, $event, array_merge($context, $requestContext), $level);
    }

    /**
     * Log authentication attempt
     */
    public function logLoginAttempt(string $email, string $ipAddress, string $userAgent, array $additionalContext = []): void
    {
        $context = array_merge([
            'email' => $email,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'attempt_time' => Carbon::now()->toIso8601String(),
        ], $additionalContext);

        $this->logAuthFlow(self::EVENT_LOGIN_ATTEMPT, $context);
    }

    /**
     * Log successful login
     */
    public function logLoginSuccess(int $userId, string $email, string $ipAddress, array $additionalContext = []): void
    {
        $context = array_merge([
            'user_id' => $userId,
            'email' => $email,
            'ip_address' => $ipAddress,
            'login_time' => Carbon::now()->toIso8601String(),
        ], $additionalContext);

        $this->logAuthFlow(self::EVENT_LOGIN_SUCCESS, $context);
    }

    /**
     * Log failed login
     */
    public function logLoginFailed(string $email, string $reason, string $ipAddress, array $additionalContext = []): void
    {
        $context = array_merge([
            'email' => $email,
            'failure_reason' => $reason,
            'ip_address' => $ipAddress,
            'failed_time' => Carbon::now()->toIso8601String(),
        ], $additionalContext);

        $this->logAuthFlow(self::EVENT_LOGIN_FAILED, $context, self::LEVEL_WARNING);
    }

    /**
     * Log token issuance
     */
    public function logTokenIssued(int $userId, string $appKey, string $tokenPreview, int $ttl, array $additionalContext = []): void
    {
        $context = array_merge([
            'user_id' => $userId,
            'app_key' => $appKey,
            'token_preview' => $tokenPreview,
            'ttl_seconds' => $ttl,
            'issued_at' => Carbon::now()->toIso8601String(),
            'expires_at' => Carbon::now()->addSeconds($ttl)->toIso8601String(),
        ], $additionalContext);

        $this->logTokenManagement(self::EVENT_TOKEN_ISSUED, $context);
    }

    /**
     * Log token verification
     */
    public function logTokenVerified(string $tokenPreview, array $payload, array $additionalContext = []): void
    {
        $context = array_merge([
            'token_preview' => $tokenPreview,
            'user_id' => $payload['sub'] ?? null,
            'app_key' => $payload['app'] ?? null,
            'issuer' => $payload['iss'] ?? null,
            'verified_at' => Carbon::now()->toIso8601String(),
            'token_expires_at' => isset($payload['exp']) ? Carbon::createFromTimestamp($payload['exp'])->toIso8601String() : null,
        ], $additionalContext);

        $this->logTokenManagement(self::EVENT_TOKEN_VERIFIED, $context);
    }

    /**
     * Log token verification failure
     */
    public function logTokenVerificationFailed(string $tokenPreview, string $reason, array $additionalContext = []): void
    {
        $context = array_merge([
            'token_preview' => $tokenPreview,
            'failure_reason' => $reason,
            'failed_at' => Carbon::now()->toIso8601String(),
        ], $additionalContext);

        $this->logTokenManagement(self::EVENT_TOKEN_INVALID, $context, self::LEVEL_WARNING);
    }

    /**
     * Log SSO redirect
     */
    public function logSsoRedirect(int $userId, string $appKey, string $callbackUrl, array $additionalContext = []): void
    {
        $context = array_merge([
            'user_id' => $userId,
            'app_key' => $appKey,
            'callback_url' => $callbackUrl,
            'redirect_time' => Carbon::now()->toIso8601String(),
        ], $additionalContext);

        $this->logAuthFlow(self::EVENT_SSO_REDIRECT, $context);
    }

    /**
     * Log security violation
     */
    public function logSecurityViolation(string $violation, array $context = []): void
    {
        $context = array_merge([
            'violation_type' => $violation,
            'detected_at' => Carbon::now()->toIso8601String(),
        ], $context);

        $this->logSecurity(self::EVENT_SECURITY_VIOLATION, $context, self::LEVEL_ERROR);
    }

    /**
     * Log rate limiting
     */
    public function logRateLimit(string $identifier, string $action, array $context = []): void
    {
        $context = array_merge([
            'identifier' => $identifier,
            'action' => $action,
            'limited_at' => Carbon::now()->toIso8601String(),
        ], $context);

        $this->logSecurity(self::EVENT_RATE_LIMIT, $context, self::LEVEL_WARNING);
    }

    /**
     * Core logging method
     */
    private function log(string $category, string $event, array $context = [], string $level = self::LEVEL_INFO): void
    {
        $logData = [
            'sso_category' => $category,
            'sso_event' => $event,
            'timestamp' => Carbon::now()->toIso8601String(),
            'context' => $context,
        ];

        $message = sprintf('[SSO:%s] %s', strtoupper($category), $event);

        match ($level) {
            self::LEVEL_DEBUG => Log::debug($message, $logData),
            self::LEVEL_INFO => Log::info($message, $logData),
            self::LEVEL_WARNING => Log::warning($message, $logData),
            self::LEVEL_ERROR => Log::error($message, $logData),
            default => Log::info($message, $logData),
        };
    }

    /**
     * Build request context
     */
    private function buildRequestContext(Request $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'query_params' => $request->query(),
            'user_id' => $request->user()?->id,
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
        ];
    }

    /**
     * Sanitize headers untuk logging (remove sensitive info)
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];

        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['[REDACTED]'];
            }
        }

        return $headers;
    }
}
