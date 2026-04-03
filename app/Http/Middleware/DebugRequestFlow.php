<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DebugRequestFlow
{
    /**
     * Handle an incoming request - log comprehensive request details.
     * This middleware runs FIRST to capture all requests.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();
        $method = $request->getMethod();
        $auth = auth()->check() ? 'YES' : 'NO';
        $sessionId = session()->getId() ?? 'NO_SESSION';
        $ip = $request->ip();

        // Log at start of request
        \Log::info('[DebugRequestFlow] REQUEST_START | ' . $method . ' ' . $path . ' | Auth: ' . $auth . ' | Session: ' . substr($sessionId, 0, 10));

        // Call next middleware
        $response = $next($request);

        // Log response after middleware chain
        $statusCode = $response->getStatusCode();
        $responseAuth = auth()->check() ? 'YES' : 'NO';

        // Check if response is a redirect
        $isRedirect = $statusCode >= 300 && $statusCode < 400;
        $redirectTarget = $isRedirect ? ($response->headers->get('Location') ?? 'UNKNOWN') : 'N/A';

        \Log::info('[DebugRequestFlow] REQUEST_END | ' . $method . ' ' . $path . ' | Status: ' . $statusCode . ' | Auth: ' . $responseAuth . ($isRedirect ? ' | Redirect: ' . $redirectTarget : ''));

        return $response;
    }
}
