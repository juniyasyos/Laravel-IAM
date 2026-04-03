<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        $path = $request->getPathInfo();
        $isJson = $request->expectsJson();

        if (! $isJson) {
            \Log::warning('[Authenticate] UNAUTHENTICATED USER | Path: ' . $path . ' | Redirecting to login');
            return route('login');
        }

        \Log::info('[Authenticate] UNAUTHENTICATED JSON REQUEST | Path: ' . $path . ' | Allowing (JSON)');
        return null;
    }
}
