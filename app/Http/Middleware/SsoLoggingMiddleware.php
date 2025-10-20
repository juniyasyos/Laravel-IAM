<?php

namespace App\Http\Middleware;

use App\Services\Sso\SsoLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk logging semua request SSO
 *
 * Middleware ini akan:
 * 1. Log setiap request yang masuk ke endpoint SSO
 * 2. Track timing dan performance
 * 3. Monitor rate limiting dan security events
 * 4. Log response status dan error
 */
class SsoLoggingMiddleware
{
    public function __construct(private readonly SsoLogger $logger)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if this is an SSO request
        if (!$this->isSsoRequest($request)) {
            return $next($request);
        }

        $trackingId = $this->logger->startPerformanceTracking('sso_middleware');
        $requestId = uniqid('req_');

        // Log incoming request
        $this->logIncomingRequest($request, $requestId);

        // Process request and capture response
        $response = $next($request);

        // Log outgoing response
        $this->logOutgoingResponse($request, $response, $requestId);

        // End performance tracking
        $this->logger->endPerformanceTracking($trackingId, [
            'request_id' => $requestId,
            'endpoint' => $this->getEndpointType($request),
            'response_status' => $response->getStatusCode(),
            'request_method' => $request->method(),
        ]);

        return $response;
    }

    /**
     * Check if request is related to SSO
     */
    private function isSsoRequest(Request $request): bool
    {
        $path = $request->path();

        return Str::contains($path, [
            'sso/redirect',
            'sso/verify',
            'oauth/userinfo',
        ]) || $request->query->has('app') || $request->has('token');
    }

    /**
     * Log incoming request
     */
    private function logIncomingRequest(Request $request, string $requestId): void
    {
        $endpointType = $this->getEndpointType($request);

        $context = [
            'request_id' => $requestId,
            'endpoint_type' => $endpointType,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
            'content_type' => $request->header('content-type'),
            'accept' => $request->header('accept'),
            'query_params' => $request->query(),
            'has_token' => $request->has('token'),
            'has_app_param' => $request->has('app'),
            'user_id' => $request->user()?->id,
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
        ];

        // Add request body info for POST requests (sanitized)
        if ($request->isMethod('POST')) {
            $context['request_size'] = strlen($request->getContent());
            $context['has_request_body'] = !empty($request->getContent());

            // Log form data keys (not values for security)
            if ($request->isJson()) {
                $context['is_json_request'] = true;
            } else {
                $context['form_keys'] = array_keys($request->all());
            }
        }

        $this->logger->logWithRequest(
            $request,
            SsoLogger::CATEGORY_AUTH_FLOW,
            'request_incoming',
            $context
        );

        // Log potential security issues
        $this->checkForSecurityIssues($request, $context);
    }

    /**
     * Log outgoing response
     */
    private function logOutgoingResponse(Request $request, Response $response, string $requestId): void
    {
        $endpointType = $this->getEndpointType($request);
        $statusCode = $response->getStatusCode();

        $context = [
            'request_id' => $requestId,
            'endpoint_type' => $endpointType,
            'status_code' => $statusCode,
            'status_text' => Response::$statusTexts[$statusCode] ?? 'Unknown',
            'response_size' => strlen($response->getContent()),
            'content_type' => $response->headers->get('content-type'),
            'is_redirect' => $response->isRedirection(),
            'is_success' => $response->isSuccessful(),
            'is_error' => $response->isClientError() || $response->isServerError(),
        ];

        // Add redirect info if applicable
        if ($response->isRedirection()) {
            $location = $response->headers->get('location');
            $context['redirect_location'] = $location;
            $context['is_external_redirect'] = $location && !Str::startsWith($location, config('app.url'));
        }

        // Add error details for error responses
        if ($response->isClientError() || $response->isServerError()) {
            $context['error_response'] = true;

            // Try to extract error message from JSON response
            if (Str::contains($response->headers->get('content-type', ''), 'json')) {
                try {
                    $responseData = json_decode($response->getContent(), true);
                    if (isset($responseData['message'])) {
                        $context['error_message'] = $responseData['message'];
                    }
                } catch (\Exception $e) {
                    // Ignore JSON decode errors
                }
            }
        }

        $logLevel = $this->getLogLevelForResponse($response);

        // For SSO flow, redirects are considered successful
        $isSuccess = $response->isSuccessful() || ($response->isRedirection() && $this->isSsoRequest($request));
        $event = $isSuccess ? 'request_completed' : 'request_failed';

        $this->logger->logWithRequest(
            $request,
            SsoLogger::CATEGORY_AUTH_FLOW,
            $event,
            $context,
            $logLevel
        );
    }

    /**
     * Get endpoint type based on request
     */
    private function getEndpointType(Request $request): string
    {
        $path = $request->path();

        if (Str::contains($path, 'sso/redirect')) {
            return 'sso_redirect';
        }

        if (Str::contains($path, 'sso/verify')) {
            return 'sso_verify';
        }

        if (Str::contains($path, 'oauth/userinfo')) {
            return 'oauth_userinfo';
        }

        if ($request->has('app')) {
            return 'sso_login';
        }

        return 'unknown_sso';
    }

    /**
     * Get appropriate log level for response
     */
    private function getLogLevelForResponse(Response $response): string
    {
        if ($response->isServerError()) {
            return SsoLogger::LEVEL_ERROR;
        }

        if ($response->isClientError()) {
            return SsoLogger::LEVEL_WARNING;
        }

        return SsoLogger::LEVEL_INFO;
    }

    /**
     * Check for security issues in the request
     */
    private function checkForSecurityIssues(Request $request, array $context): void
    {
        // Check for suspicious user agents
        $userAgent = $request->userAgent();
        if ($this->isSuspiciousUserAgent($userAgent)) {
            $this->logger->logSecurity('suspicious_user_agent', [
                'user_agent' => $userAgent,
                'ip_address' => $request->ip(),
                'request_id' => $context['request_id'],
            ]);
        }

        // Check for multiple rapid requests from same IP
        $this->checkRateLimit($request, $context);

        // Check for malformed tokens
        if ($request->has('token')) {
            $token = $request->get('token');
            if (!$this->isValidTokenFormat($token)) {
                $this->logger->logSecurity('malformed_token_format', [
                    'token_preview' => substr($token, 0, 20) . '...',
                    'token_length' => strlen($token),
                    'ip_address' => $request->ip(),
                    'request_id' => $context['request_id'],
                ]);
            }
        }

        // Check for missing required parameters
        $this->checkRequiredParameters($request, $context);
    }

    /**
     * Check if user agent is suspicious
     */
    private function isSuspiciousUserAgent(?string $userAgent): bool
    {
        if (empty($userAgent)) {
            return true;
        }

        $suspiciousPatterns = [
            'bot',
            'crawler',
            'spider',
            'scraper',
            'wget',
            'curl',
            'python',
            'script',
        ];

        $userAgentLower = strtolower($userAgent);

        foreach ($suspiciousPatterns as $pattern) {
            if (Str::contains($userAgentLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimit(Request $request, array $context): void
    {
        $cacheKey = 'sso_requests:' . $request->ip();
        $requests = cache()->get($cacheKey, []);
        $now = time();

        // Remove requests older than 1 minute
        $requests = array_filter($requests, fn($timestamp) => $now - $timestamp < 60);

        // Add current request
        $requests[] = $now;

        // Store back to cache
        cache()->put($cacheKey, $requests, 60);

        // Check if rate limit exceeded (more than 30 requests per minute)
        if (count($requests) > 30) {
            $this->logger->logRateLimit(
                $request->ip(),
                'sso_requests',
                [
                    'request_count' => count($requests),
                    'time_window' => '1_minute',
                    'request_id' => $context['request_id'],
                    'endpoint_type' => $context['endpoint_type'],
                ]
            );
        }
    }

    /**
     * Check if token format is valid
     */
    private function isValidTokenFormat(string $token): bool
    {
        // JWT should have 3 parts separated by dots
        $parts = explode('.', $token);
        return count($parts) === 3 && !empty($parts[0]) && !empty($parts[1]) && !empty($parts[2]);
    }

    /**
     * Check required parameters based on endpoint
     */
    private function checkRequiredParameters(Request $request, array $context): void
    {
        $endpointType = $context['endpoint_type'];

        switch ($endpointType) {
            case 'sso_redirect':
                if (!$request->has('app')) {
                    $this->logger->logSecurity('missing_required_parameter', [
                        'missing_parameter' => 'app',
                        'endpoint' => 'sso_redirect',
                        'request_id' => $context['request_id'],
                    ]);
                }
                break;

            case 'sso_verify':
                if (!$request->has('token')) {
                    $this->logger->logSecurity('missing_required_parameter', [
                        'missing_parameter' => 'token',
                        'endpoint' => 'sso_verify',
                        'request_id' => $context['request_id'],
                    ]);
                }
                break;
        }
    }
}
