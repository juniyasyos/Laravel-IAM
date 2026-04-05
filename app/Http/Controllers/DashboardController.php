<?php

namespace App\Http\Controllers;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\UserDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private UserDataService $userDataService,
    ) {}

    public function index()
    {
        $user = auth()->user();

        // Fetch applications by access profile - only accessible apps
        $accessProfiles = $this->userDataService->getUserApplicationsByAccessProfile($user);

        // Flatten applications for Inertia prop (component will organize by profile)
        $applications = [];
        foreach ($accessProfiles as $profile) {
            foreach ($profile['applications'] as $app) {
                // Build complete app data from profile structure
                $primaryUrl = is_array($app['redirect_uris'] ?? []) && !empty($app['redirect_uris'])
                    ? $app['redirect_uris'][0]
                    : null;

                $applications[] = [
                    'app_key' => $app['app_key'],
                    'name' => $app['name'],
                    'description' => $app['description'] ?? '',
                    'app_url' => $primaryUrl,
                    'enabled' => $app['enabled'] ?? true,
                    'logo_url' => $app['logo_url'] ?? null,
                ];
            }
        }

        return Inertia::render('Dashboard/DashboardPage', [
            'applications' => $applications,
            'accessProfiles' => $accessProfiles,
        ]);
    }
}
