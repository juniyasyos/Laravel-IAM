<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check IAM admin access.
 * Uses configurable rules from config/iam.php
 */
class CheckIAMAdmin
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

        // Check access based on config
        if (!$this->checkAccess($user)) {
            $message = config('iam.admin_access.denied_message', 'Access denied.');
            $redirect = config('iam.admin_access.denied_redirect');

            if ($redirect) {
                return redirect($redirect)->with('error', $message);
            }

            abort(403, $message);
        }

        return $next($request);
    }

    /**
     * Check if user has access based on configured rules.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    protected function checkAccess($user): bool
    {
        $method = config('iam.admin_access.method', 'email');
        
        $emailCheck = $this->checkEmail($user);
        $callbackCheck = $this->checkCallback($user);

        return match ($method) {
            'email' => $emailCheck,
            'callback' => $callbackCheck,
            'both' => $emailCheck && $callbackCheck,
            'either' => $emailCheck || $callbackCheck,
            default => $emailCheck,
        };
    }

    /**
     * Check if user email is in whitelist.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    protected function checkEmail($user): bool
    {
        $allowedEmails = config('iam.admin_access.allowed_emails', []);
        
        if (empty($allowedEmails)) {
            return false;
        }

        return in_array($user->email, $allowedEmails);
    }

    /**
     * Check using custom callback.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    protected function checkCallback($user): bool
    {
        $callback = config('iam.admin_access.callback');
        
        if (!is_callable($callback)) {
            return false;
        }

        return (bool) $callback($user);
    }
}
