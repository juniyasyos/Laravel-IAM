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

    /**
     * Get the currently authenticated user\'s accessible applications.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function applications(Request $request): JsonResponse
    {
        $user = $request->user();

        $userData = $this->userDataService->getUserData(
            user: $user,
            application: null,
            includeProfiles: false
        );

        return response()->json([
            'sub' => (string) $user->id,
            'user_id' => $user->id,
            'applications' => $userData['applications'] ?? [],
            'accessible_apps' => $userData['accessible_apps'] ?? [],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function accessProfiles(Request $request): JsonResponse
    {
        $user = $request->user();

        // Through getUserData() we get effective profiles; but for debugging we return
        // direct assigned access profiles from user relation to avoid out-of-sync logic.
        $profiles = $user->accessProfiles()
            ->with(['roles.application'])
            ->get()
            ->map(function ($profile) {
                return [
                    'id' => $profile->id,
                    'slug' => $profile->slug,
                    'name' => $profile->name,
                    'description' => $profile->description,
                    'is_active' => $profile->is_active,
                    'roles' => $profile->roles->map(fn($role) => [
                        'app_key' => $role->application?->app_key,
                        'role_slug' => $role->slug,
                        'role_name' => $role->name,
                    ])->toArray(),
                ];
            });

        return response()->json([
            'sub' => (string) $user->id,
            'user_id' => $user->id,
            'test' => 'This endpoint returns directly assigned access profiles with their roles. It does not compute effective profiles/roles.',
            'access_profiles' => $profiles,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
