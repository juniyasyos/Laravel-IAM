<?php

namespace App\Http\Middleware;

use App\Exceptions\AccessDeniedException;
use App\Exceptions\InvalidApiKeyException;
use App\Exceptions\RateLimitExceededException;
use App\Models\IntegrationKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('X-API-Key', '');
        $apiKey = trim($header);

        if ($apiKey === '') {
            throw new InvalidApiKeyException('Missing X-API-Key header.');
        }

        if (! preg_match('/^[A-Za-z0-9\-_]+$/', $apiKey)) {
            throw new InvalidApiKeyException('Invalid API key format.');
        }

        $integrationKey = IntegrationKey::where('key', $apiKey)->first();
        if (! $integrationKey) {
            throw new InvalidApiKeyException('Invalid API key.');
        }

        if (! $request->isSecure() && ! app()->environment('local', 'testing')) {
            throw new AccessDeniedException('HTTPS is required for this API.');
        }

        $this->enforceRateLimits($request, $integrationKey);

        $request->attributes->set('integration_key', $integrationKey);

        return $next($request);
    }

    private function enforceRateLimits(Request $request, IntegrationKey $integrationKey): void
    {
        $hashedKey = hash('sha256', $integrationKey->key);
        $keyLimiter = "ttd-url:key:{$hashedKey}";
        $ipLimiter = "ttd-url:ip:{$request->ip()}";

        if (RateLimiter::tooManyAttempts($keyLimiter, 100)) {
            throw new RateLimitExceededException('Rate limit exceeded for this API key.');
        }

        if (RateLimiter::tooManyAttempts($ipLimiter, 1000)) {
            throw new RateLimitExceededException('Rate limit exceeded for this IP address.');
        }

        RateLimiter::hit($keyLimiter, 60);
        RateLimiter::hit($ipLimiter, 60);
    }
}
