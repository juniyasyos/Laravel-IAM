<?php

namespace App\Http\Controllers;

use App\Domain\Iam\Models\Application;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Fetch applications for sidebar/picker
        $applications = Application::where('enabled', true)
            ->orderBy('name')
            ->get()
            ->map(function ($app) {
                return [
                    'app_key' => $app->app_key,
                    'name' => $app->name,
                    'description' => $app->description ?? '',
                    'app_url' => $app->app_url,
                    'enabled' => $app->enabled,
                    'logo_url' => $app->logo_url ?? null,
                ];
            })
            ->toArray();

        return Inertia::render('Dashboard/DashboardPage', [
            'applications' => $applications,
        ]);
    }
}
