<?php

namespace App\Http\Controllers;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\UserDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserInfoController extends Controller
{
    public function __construct(
        private readonly UserDataService $userDataService
    ) {}

    /**
     * Get comprehensive user information.
     * 
     * This endpoint returns detailed user data including:
     * - Basic user info (id, name, email, status)
     * - All effective roles (direct + via access profiles)
     * - Access profiles with role breakdown
     * - Accessible applications
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $application = null;
        if ($request->filled('app')) {
            $application = Application::where('app_key', $request->query('app'))
                ->enabled()
                ->firstOrFail();
        }

        $includeProfiles = $request->boolean('include_profiles', true);
        
        $userData = $this->userDataService->getUserData(
            user: $user,
            application: $application,
            includeProfiles: $includeProfiles
        );

        return response()->json([
            'sub' => (string) $user->id,
            'user' => $userData,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
