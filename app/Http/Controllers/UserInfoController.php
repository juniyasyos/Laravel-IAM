<?php

namespace App\Http\Controllers;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\UserDataService;
use App\Models\User;
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
     * Get the currently authenticated user's accessible applications with metadata.
     * Organized by access profiles.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function applications(Request $request): JsonResponse
    {
        $user = $request->user();

        $accessProfiles = $this->userDataService->getUserApplicationsByAccessProfile($user);

        return response()->json([
            'sub' => (string) $user->id,
            'user_id' => $user->id,
            'total_profiles' => count($accessProfiles),
            'total_accessible_apps' => collect($accessProfiles)
                ->sum(fn($p) => $p['applications_count']),
            'access_profiles' => $accessProfiles,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get detailed information about user's accessible applications.
     * Includes metadata, logos, URLs, and complete role information.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function applicationsDetail(Request $request): JsonResponse
    {
        $user = $request->user();

        $userData = $this->userDataService->getUserData(
            user: $user,
            application: null,
            includeProfiles: true
        );

        // Get all accessible apps from user's effective roles
        $accessibleAppKeys = $userData['accessible_apps'] ?? [];

        // Fetch full app details including creation metadata
        $appDetails = Application::whereIn('app_key', $accessibleAppKeys)
            ->where('enabled', true)
            ->get();

        // OPTIMIZATION: Load user profiles once instead of per-application
        $userProfilesByApp = $this->getUserProfilesByApp($user);

        $appDetails = $appDetails->map(function ($app) use ($userData, $user, $userProfilesByApp) {
            // Find corresponding application data from userData
            $appData = collect($userData['applications'] ?? [])
                ->firstWhere('app_key', $app->app_key) ?? [];

            return [
                'id' => $app->id,
                'app_key' => $app->app_key,
                'name' => $app->name,
                'description' => $app->description,
                'status' => $app->enabled ? 'active' : 'inactive',
                'metadata' => [
                    'logo' => [
                        'url' => $app->logo_url,
                        'available' => !empty($app->logo_url),
                    ],
                    'urls' => [
                        'primary' => is_array($app->redirect_uris) && !empty($app->redirect_uris)
                            ? $app->redirect_uris[0]
                            : null,
                        'all_redirects' => $app->redirect_uris ?? [],
                        'callback' => $app->callback_url,
                        'backchannel' => $app->backchannel_url,
                    ],
                    'created_at' => $app->created_at?->toIso8601String(),
                    'updated_at' => $app->updated_at?->toIso8601String(),
                ],
                'roles' => $appData['roles'] ?? [],
                'roles_count' => count($appData['roles'] ?? []),
                'access_profiles_using_this_app' => $userProfilesByApp[$app->id] ?? [],
            ];
        })
            ->sortBy('name')
            ->values()
            ->toArray();

        $appDetails = $this->prependIamHomeApplication($appDetails, true);

        return response()->json([
            'sub' => (string) $user->id,
            'user_id' => $user->id,
            'total_apps' => count($appDetails),
            'applications' => $appDetails,
            'user_profiles' => $userData['access_profiles'] ?? [],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    private function prependIamHomeApplication(array $applications, bool $detailed): array
    {
        $homeConfig = config('iam.home_app', []);
        $enabled = (bool) ($homeConfig['enabled'] ?? true);

        if (!$enabled) {
            return $applications;
        }

        $homeApp = $this->buildIamHomeApplication($detailed);

        if (empty($homeApp['app_key'])) {
            return $applications;
        }

        $filtered = collect($applications)
            ->reject(fn($app) => ($app['app_key'] ?? null) === $homeApp['app_key'])
            ->values()
            ->toArray();

        array_unshift($filtered, $homeApp);

        return $filtered;
    }

    private function buildIamHomeApplication(bool $detailed): array
    {
        $homeConfig = config('iam.home_app', []);

        $appKey = (string) ($homeConfig['app_key'] ?? 'iam-home');
        $name = (string) ($homeConfig['name'] ?? 'IAM Home');
        $description = (string) ($homeConfig['description'] ?? 'Portal utama IAM');
        $url = (string) ($homeConfig['url'] ?? 'http://127.0.0.1:8010/');
        $logoUrl = $homeConfig['logo_url'] ?? null;

        if ($detailed) {
            return [
                'id' => null,
                'app_key' => $appKey,
                'name' => $name,
                'description' => $description,
                'status' => 'active',
                'metadata' => [
                    'logo' => [
                        'url' => $logoUrl,
                        'available' => !empty($logoUrl),
                    ],
                    'urls' => [
                        'primary' => $url,
                        'all_redirects' => [$url],
                        'callback' => null,
                        'backchannel' => null,
                    ],
                    'created_at' => null,
                    'updated_at' => null,
                ],
                'roles' => [],
                'roles_count' => 0,
                'access_profiles_using_this_app' => [],
            ];
        }

        return [
            'id' => null,
            'app_key' => $appKey,
            'name' => $name,
            'description' => $description,
            'enabled' => true,
            'logo_url' => $logoUrl,
            'app_url' => $url,
            'redirect_uris' => [$url],
            'roles' => [],
            'roles_count' => 0,
            'status' => 'active',
            'has_logo' => !empty($logoUrl),
            'has_primary_url' => true,
            'urls' => [
                'primary' => $url,
                'all_redirects' => [$url],
            ],
        ];
    }

    /**
     * Get access profiles that provide access to a specific application.
     * 
     * @param User $user
     * @param int $appId
     * @return array
     */
    /**
     * OPTIMIZATION: Load user profiles grouped by application once.
     * Prevents N+1 queries when accessed in loops.
     *
     * @param User $user
     * @return array<int, array> Profiles grouped by app_id
     */
    private function getUserProfilesByApp(User $user): array
    {
        return $user->accessProfiles()
            ->with('roles:id,application_id,slug,name')
            ->get()
            ->flatMap(function ($profile) {
                // Group this profile by each application it has roles for
                return $profile->roles->groupBy('application_id')->map(function ($appRoles, $appId) use ($profile) {
                    return [
                        'app_id' => $appId,
                        'profile' => [
                            'id' => $profile->id,
                            'name' => $profile->name,
                            'slug' => $profile->slug,
                        ],
                    ];
                });
            })
            ->groupBy('app_id')
            ->map(function ($appProfiles) {
                return $appProfiles->map(fn($item) => $item['profile'])->values()->toArray();
            })
            ->toArray();
    }

    private function getProfilesUsingApp(User $user, int $appId): array
    {
        return $user->accessProfiles()
            ->with('roles')
            ->get()
            ->filter(function ($profile) use ($appId) {
                return $profile->roles->contains('application_id', $appId);
            })
            ->map(fn($profile) => [
                'id' => $profile->id,
                'name' => $profile->name,
                'slug' => $profile->slug,
            ])
            ->values()
            ->toArray();
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
