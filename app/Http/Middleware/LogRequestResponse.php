<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogRequestResponse
{
    /**
     * Log incoming request and outgoing response for debugging.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = substr(md5(microtime()), 0, 8);
        $method = $request->getMethod();
        $path = $request->getPathInfo();
        $auth = auth()->check() ? 'AUTH:' . auth()->user()->nip : 'ANON';
        $sessionId = session()->getId() ?? 'none';

        \Log::info("[ReqLog:$requestId] ▶ IN | $method $path | User: $auth | Session: " . substr($sessionId, 0, 10));

        $response = $next($request);

        // Log response
        $status = $response->getStatus();
        $redirectTo = $response->headers->get('Location') ?? 'none';

        if ($status >= 300 && $status < 400) {
            \Log::warning("[ReqLog:$requestId] ◀ OUT | HTTP $status REDIRECT | Location: $redirectTo");
        } else {
            \Log::info("[ReqLog:$requestId] ◀ OUT | HTTP $status | Path: $path");
        }

        return $response;
    }
}
