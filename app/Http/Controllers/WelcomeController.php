<?php

namespace App\Http\Controllers;

use App\Domain\Iam\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WelcomeController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isAuthenticated = Auth::check();

        // Get all enabled applications
        $applications = Application::where('enabled', true)
            ->orderBy('name')
            ->get()
            ->map(function ($app) use ($user) {
                return [
                    'id' => $app->app_key,
                    'name' => $app->name,
                    'code' => strtoupper(substr($app->app_key, 0, 3)),
                    'description' => $app->description ?? 'Aplikasi ' . $app->name,
                    'status' => $this->determineAppStatus($app),
                    'scope' => $this->determineAppScope($app),
                    'tags' => $this->generateAppTags($app),
                    'url' => $this->getAppUrl($app),
                    'requiresAuth' => true,
                    'logo_url' => $app->logo_url,
                ];
            });

        // User data for authenticated users
        $userData = null;
        $userInitials = '';
        if ($isAuthenticated && $user) {
            $userData = [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $this->getUserPrimaryRole($user),
            ];

            // Generate user initials
            $userInitials = collect(explode(' ', $user->name))
                ->map(fn($part) => strtoupper(substr($part, 0, 1)))
                ->take(2)
                ->join('');
        }

        // Dev autofill (like Vue Login component)
        $devAutofill = null;
        if (app()->environment('local')) {
            $devAutofill = [
                'nip' => '0000.00000',
                'password' => 'adminpassword',
            ];
        }

        return view('welcome', [
            'isAuthenticated' => $isAuthenticated,
            'user' => $userData,
            'userInitials' => $userInitials,
            'applications' => $applications,
            'devAutofill' => $devAutofill,
        ]);
    }

    /**
     * Determine application status based on configuration or metadata
     */
    private function determineAppStatus(Application $app): string
    {
        // You can add logic here to determine status
        // For now, we'll use a simple heuristic
        $statusMap = [
            'siimut' => 'ready',
            'tamasuma' => 'beta',
            'incident-report.app' => 'beta',
            'pharmacy.app' => 'ready',
            'client-example' => 'planned',
        ];

        return $statusMap[$app->app_key] ?? 'ready';
    }

    /**
     * Determine application scope/category
     */
    private function determineAppScope(Application $app): string
    {
        $scopeMap = [
            'siimut' => 'Mutu & Manajemen RS',
            'tamasuma' => 'Manajemen Unit',
            'incident-report.app' => 'Keselamatan Pasien',
            'pharmacy.app' => 'Farmasi & Obat',
            'client-example' => 'Demo & Testing',
        ];

        return $scopeMap[$app->app_key] ?? 'Aplikasi RS';
    }

    /**
     * Generate tags for application
     */
    private function generateAppTags(Application $app): array
    {
        $tagMap = [
            'siimut' => ['indikator', 'dashboard', 'mutu'],
            'tamasuma' => ['unit-kerja', 'manajemen'],
            'incident-report.app' => ['insiden', 'keselamatan', 'audit'],
            'pharmacy.app' => ['farmasi', 'obat', 'inventory'],
            'client-example' => ['demo', 'contoh'],
        ];

        return $tagMap[$app->app_key] ?? ['aplikasi'];
    }

    /**
     * Get application URL
     */
    private function getAppUrl(Application $app): string
    {
        // Use the first redirect URI or construct SSO URL
        if (!empty($app->redirect_uris) && is_array($app->redirect_uris)) {
            return $app->redirect_uris[0];
        }

        // Fallback to SSO endpoint
        return route('sso.authorize', ['app_key' => $app->app_key]);
        // return route('oauth.authorize', ['app_key' => $app->app_key]);
    }

    /**
     * Get user's primary role
     */
    private function getUserPrimaryRole($user): string
    {
        if (!$user) {
            return 'Guest';
        }

        // Try to get role from IAM system
        try {
            $roles = $user->applicationRoles()
                ->with('application')
                ->get();

            if ($roles->isNotEmpty()) {
                $firstRole = $roles->first();
                return ucfirst($firstRole->slug) . ' - ' . $firstRole->application->name;
            }
        } catch (\Exception $e) {
            // Continue to fallback
        }

        // Fallback to Spatie roles if available
        try {
            if (method_exists($user, 'getRoleNames')) {
                $spatieRoles = $user->getRoleNames();
                if ($spatieRoles->isNotEmpty()) {
                    return ucfirst($spatieRoles->first());
                }
            }
        } catch (\Exception $e) {
            // Continue to final fallback
        }

        return 'User';
    }
}
