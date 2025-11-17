<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Sso\SsoLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly SsoLogger $logger) {}

    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        if ($request->filled('app')) {
            $appKey = $request->string('app')->toString();

            $request->session()->put('sso.intended_app', $appKey);
            $request->session()->put(
                'url.intended',
                route('sso.redirect', ['app' => $appKey], absolute: true)
            );

            // Log SSO login page access
            $this->logger->logWithRequest($request, SsoLogger::CATEGORY_AUTH_FLOW, 'sso_login_page_accessed', [
                'app_key' => $appKey,
                'intended_url' => route('sso.redirect', ['app' => $appKey], absolute: true),
            ]);
        }

        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'devAutofill' => app()->environment('local') ? [
                'email' => 'admin@gmail.com',
                'password' => 'password',
            ] : null,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): \Symfony\Component\HttpFoundation\Response
    {
        $trackingId = $this->logger->startPerformanceTracking('login_process');
        $email = $request->string('email')->toString();

        // Log login attempt
        $this->logger->logLoginAttempt(
            email: $email,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent() ?? 'Unknown',
            additionalContext: [
                'has_sso_context' => $request->session()->has('sso.intended_app'),
                'intended_app' => $request->session()->get('sso.intended_app'),
                'remember_me' => $request->boolean('remember'),
            ]
        );

        try {
            $user = $request->validateCredentials();

            if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
                $request->session()->put([
                    'login.id' => $user->getKey(),
                    'login.remember' => $request->boolean('remember'),
                ]);

                // Log 2FA redirect
                $this->logger->logAuthFlow('two_factor_redirect', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip_address' => $request->ip(),
                ]);

                $this->logger->endPerformanceTracking($trackingId, [
                    'login_successful' => true,
                    'requires_2fa' => true,
                    'user_id' => $user->id,
                ]);

                return to_route('two-factor.login');
            }

            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            // Log successful login
            $this->logger->logLoginSuccess(
                userId: $user->id,
                email: $user->email,
                ipAddress: $request->ip(),
                additionalContext: [
                    'remember_me' => $request->boolean('remember'),
                    'has_sso_context' => $request->session()->has('sso.intended_app'),
                ]
            );

            // Default redirect to home page (root) instead of panel
            $intended = $request->session()->pull('url.intended', route('home', absolute: true));

            if ($request->session()->has('sso.intended_app')) {
                $appKey = (string) $request->session()->pull('sso.intended_app');

                // Log SSO flow completion
                $this->logger->logAuthFlow('sso_login_completed', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'app_key' => $appKey,
                    'redirect_url' => route('sso.redirect', ['app' => $appKey], absolute: true),
                    'ip_address' => $request->ip(),
                ]);

                $this->logger->endPerformanceTracking($trackingId, [
                    'login_successful' => true,
                    'sso_flow' => true,
                    'app_key' => $appKey,
                    'user_id' => $user->id,
                ]);

                return Inertia::location(
                    route('sso.redirect', ['app' => $appKey], absolute: true)
                );
            }

            $this->logger->endPerformanceTracking($trackingId, [
                'login_successful' => true,
                'sso_flow' => false,
                'user_id' => $user->id,
            ]);

            return Inertia::location($intended);
        } catch (\Throwable $exception) {
            // Log login failure
            $this->logger->logLoginFailed(
                email: $email,
                reason: $exception->getMessage(),
                ipAddress: $request->ip(),
                additionalContext: [
                    'exception_class' => get_class($exception),
                    'has_sso_context' => $request->session()->has('sso.intended_app'),
                    'intended_app' => $request->session()->get('sso.intended_app'),
                ]
            );

            $this->logger->endPerformanceTracking($trackingId, [
                'login_successful' => false,
                'error' => $exception->getMessage(),
                'email' => $email,
            ]);

            throw $exception;
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            // Log logout
            $this->logger->logAuthFlow('logout', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
                'session_id' => $request->session()->getId(),
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
