<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check Pulse dashboard access.
 * Can use IAM admin rules or separate configuration.
 */
class CheckPulseAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Check if should use IAM admin rules
        if (config('iam.pulse_access.use_iam_admin_rules', true)) {
            return $this->checkIAMAdminRules($user, $request, $next);
        }

        // Use separate Pulse rules
        if (!$this->checkPulseAccess($user)) {
            abort(403, 'Access denied. Only authorized users can access Pulse dashboard.');
        }

        return $next($request);
    }

    /**
     * Check using IAM admin rules.
     */
    protected function checkIAMAdminRules($user, Request $request, Closure $next): Response
    {
        $middleware = new CheckIAMAdmin();
        return $middleware->handle($request, $next);
    }

    /**
     * Check Pulse-specific access rules.
     */
    protected function checkPulseAccess($user): bool
    {
        // Check email whitelist
        $allowedEmails = config('iam.pulse_access.allowed_emails', []);
        if (!empty($allowedEmails) && in_array($user->email, $allowedEmails)) {
            return true;
        }

        // Check callback
        $callback = config('iam.pulse_access.callback');
        if (is_callable($callback)) {
            return (bool) $callback($user);
        }

        return false;
    }
}
