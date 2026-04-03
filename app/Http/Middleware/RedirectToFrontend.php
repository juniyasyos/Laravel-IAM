<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToFrontend
{
    /**
     * Frontend port to redirect to.
     */
    protected string $frontendPort = '3100';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();
        $authCheck = auth()->check() ? 'YES' : 'NO';
        $sessionId = session()->getId();
        \Log::info('[RedirectToFrontend] START | Path: ' . $path . ' | Auth: ' . $authCheck . ' | Session: ' . $sessionId);

        // Don't redirect API routes
        if (str_starts_with($path, '/api') || str_starts_with($path, '/sso')) {
            \Log::info('[RedirectToFrontend] SKIP (API/SSO) | Path: ' . $path);
            return $next($request);
        }

        // Don't redirect auth session creation endpoint
        if (str_starts_with($path, '/auth/session-from-token')) {
            \Log::info('[RedirectToFrontend] SKIP (session-from-token) | Path: ' . $path);
            return $next($request);
        }

        // Don't redirect Filament admin/panel routes
        // Check both '/panel' and '/panel/' and any subpath
        if (str_starts_with($path, '/panel') || str_starts_with($path, '/admin')) {
            \Log::info('[RedirectToFrontend] SKIP (panel/admin) | Path: ' . $path);
            return $next($request);
        }

        // Check if frontend is accessible on port 3100
        $frontendAccessible = $this->isFrontendAccessible();
        if ($frontendAccessible) {
            $frontendUrl = $this->getFrontendUrl($path);
            \Log::info('[RedirectToFrontend] REDIRECTING | Path: ' . $path . ' → ' . $frontendUrl);
            return redirect($frontendUrl);
        }

        // If frontend not accessible, continue with Laravel
        \Log::info('[RedirectToFrontend] CONTINUE (FE not accessible) | Path: ' . $path);
        return $next($request);
    }

    /**
     * Get the frontend host.
     */
    protected function getFrontendHost(): string
    {
        // Prioritize environment variable, fallback to current request host
        return env('FRONTEND_HOST') ?? request()->getHost();
    }

    /**
     * Get the frontend URL.
     */
    protected function getFrontendUrl(string $path = ''): string
    {
        $scheme = 'http';
        $host = $this->getFrontendHost();
        $port = $this->frontendPort;

        // Use https if current request is https
        if (request()->secure()) {
            $scheme = 'https';
        }

        // Construct URL
        $baseUrl = "{$scheme}://{$host}:{$port}";

        // Remove leading slash for concatenation
        if ($path === '/' || $path === '') {
            return $baseUrl;
        }

        return $baseUrl . $path;
    }

    /**
     * Check if frontend is accessible on the configured port.
     */
    protected function isFrontendAccessible(): bool
    {
        try {
            $host = $this->getFrontendHost();
            $timeout = 1;

            \Log::debug('[RedirectToFrontend.isFrontendAccessible] Check | Host: ' . $host . ':' . $this->frontendPort);

            // Try to open a connection to the frontend
            $fp = @fsockopen($host, $this->frontendPort, $errno, $errstr, $timeout);

            if (is_resource($fp)) {
                fclose($fp);
                \Log::debug('[RedirectToFrontend.isFrontendAccessible] SUCCESS | Host reachable');
                return true;
            } else {
                \Log::debug('[RedirectToFrontend.isFrontendAccessible] FAILED | Error: ' . $errstr);
            }
        } catch (\Exception $e) {
            \Log::warning('[RedirectToFrontend.isFrontendAccessible] Exception: ' . $e->getMessage());
        }

        return false;
    }
}
