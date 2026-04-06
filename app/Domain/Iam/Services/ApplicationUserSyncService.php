<?php

namespace App\Domain\Iam\Services;

use App\Domain\Iam\Models\Application;
use App\Models\User;
use App\Domain\Iam\Services\UserRoleAssignmentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\JWTTokenService;

class ApplicationUserSyncService
{
    /**
     * Optional profile IDs supplied by the caller (Filament modal). When
     * non‑empty the assignment service will restrict which bundles may be
     * attached/detached.
     *
     * @var array<int>
     */
    protected array $allowedProfileIds = [];

    /**
     * Sinkronisasi mode: `auto` atau `manual`.
     */
    protected string $syncMode = 'auto';

    /**
     * Manual role mapping per application (app_id => role slug list).
     *
     * @var array<int,array<string>>
     */
    protected array $manualRoleMapping = [];

    public function __construct(array $allowedProfileIds = [], string $syncMode = 'auto', array $manualRoleMapping = [])
    {
        $this->allowedProfileIds = $allowedProfileIds;
        $this->syncMode = $syncMode;
        $this->manualRoleMapping = $manualRoleMapping;
    }

    /**
     * Sync users (including their application roles) from client application.
     *
     * Existing users are looked up by NIP (primary) or email.  New users are
     * created with a random password and marked active by default.  After a
     * user record is obtained we delegate role assignment to the
     * UserRoleAssignmentService.
     *
     * The returned array mimics the structure of the role sync service so the
     * UI can display a summary if needed.
     */
    public function syncUsers(Application $application, ?int $userId = null): array
    {
        $mode = config('iam.user_sync_mode', 'pull');

        if ($mode === 'push') {
            return $this->pushUsersToClient($application, $userId);
        }

        $result = $this->fetchClientUsers($application);

        if (! $result['success']) {
            return $result;
        }

        $clientUsers = $result['client_users'];
        $comparison = $result['comparison'];

        $created = 0;
        $updated = 0;

        $assignmentService = new UserRoleAssignmentService();
        if (! empty($this->allowedProfileIds)) {
            $assignmentService->setAllowedProfileIds($this->allowedProfileIds);
        }

        foreach ($clientUsers as $cUser) {
            $userQuery = User::query();
            if (! empty($cUser['nip'])) {
                $userQuery->where('nip', $cUser['nip']);
            }
            if (! empty($cUser['email'])) {
                $userQuery->orWhere('email', $cUser['email']);
            }
            $user = $userQuery->first();

            if (! $user) {
                $user = User::create([
                    'nip' => $cUser['nip'] ?? null,
                    'name' => $cUser['name'] ?? null,
                    'email' => $cUser['email'] ?? null,
                    'password' => bcrypt('rschjaya1234'),
                    'active' => $cUser['active'] ?? true,
                ]);

                Log::info('iam.user_sync_created_user', [
                    'application_id' => $application->id,
                    'user_id' => $user->id,
                    'nip' => $user->nip,
                    'email' => $user->email,
                    'name' => $user->name,
                    'active' => $user->active,
                ]);

                if (empty($user->email)) {
                    Log::warning('iam.user_sync_created_user_missing_email', [
                        'application_id' => $application->id,
                        'user_id' => $user->id,
                        'nip' => $user->nip,
                        'name' => $user->name,
                    ]);
                }

                $created++;
            } else {
                $changesBefore = $user->only(['nip', 'name', 'email', 'active']);

                $forcePull = config('iam.user_sync_force_pull', false);
                if ($forcePull) {
                    $user->update([
                        'name' => $cUser['name'] ?? null,
                        'email' => $cUser['email'] ?? null,
                        'active' => array_key_exists('active', $cUser) ? $cUser['active'] : null,
                    ]);
                } else {
                    $user->update([
                        'name' => array_key_exists('name', $cUser) ? $cUser['name'] : $user->name,
                        'email' => array_key_exists('email', $cUser) ? $cUser['email'] : $user->email,
                        'active' => array_key_exists('active', $cUser) ? $cUser['active'] : $user->active,
                    ]);
                }

                $changesAfter = $user->only(['nip', 'name', 'email', 'active']);

                Log::info('iam.user_sync_updated_user', [
                    'application_id' => $application->id,
                    'user_id' => $user->id,
                    'before' => $changesBefore,
                    'after' => $changesAfter,
                ]);

                if (empty($user->email)) {
                    Log::warning('iam.user_sync_updated_user_missing_email', [
                        'application_id' => $application->id,
                        'user_id' => $user->id,
                        'nip' => $user->nip,
                        'name' => $user->name,
                    ]);
                }

                $updated++;
            }

            $roleSlugs = [];
            if ($this->syncMode === 'manual' && isset($this->manualRoleMapping[$application->id])) {
                $roleSlugs = $this->manualRoleMapping[$application->id];
            } else {
                $roleSlugs = $cUser['roles'] ?? [];
            }

            // Log role slugs received from client
            Log::info('user_sync_role_slugs_from_client', [
                'application_id' => $application->id,
                'app_key' => $application->app_key,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_nip' => $user->nip,
                'client_role_slugs' => $roleSlugs,
                'sync_mode' => $this->syncMode,
                'allowed_profile_ids' => $this->allowedProfileIds ?: 'none',
            ]);

            try {
                $assignmentService->syncProfilesForUserAndApp($user, $application, $roleSlugs);
                $assignmentService->syncProfilesFromExistingAppRoles($user, $application);
            } catch (\Exception $e) {
                Log::error('user_role_sync_failed', [
                    'application_id' => $application->id,
                    'app_key' => $application->app_key,
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'user_nip' => $user->nip,
                    'error' => $e->getMessage(),
                    'client_roles' => $roleSlugs,
                    'sync_mode' => $this->syncMode,
                    'allowed_profile_ids' => $this->allowedProfileIds ?: 'none',
                ]);
            }
        }

        return [
            'success' => true,
            'message' => "Sync completed: {$created} users created, {$updated} users updated",
            'created' => $created,
            'updated' => $updated,
            'iam_users' => $this->getIamUsers($application),
            'client_users' => $clientUsers,
            'comparison' => $comparison,
        ];
    }

    /**
     * Force pushing users to client regardless of configure mode.
     */
    public function forcePushUsers(Application $application, ?int $userId = null): array
    {
        return $this->pushUsersToClient($application, $userId);
    }

    /**
     * Prepare a dry-run preview of sync actions for the application.
     * Returns client user records with expected profile mapping, but does not
     * create or update any users.
     */
    public function previewUsers(Application $application): array
    {
        $result = $this->fetchClientUsers($application);
        if (! $result['success']) {
            return $result;
        }

        $clientUsers = $result['client_users'];
        $comparison = $result['comparison'];

        $assignmentService = new UserRoleAssignmentService();
        if (! empty($this->allowedProfileIds)) {
            $assignmentService->setAllowedProfileIds($this->allowedProfileIds);
        }

        $usersPreview = [];

        foreach ($clientUsers as $cUser) {
            $roleSlugs = [];
            if ($this->syncMode === 'manual' && isset($this->manualRoleMapping[$application->id])) {
                $roleSlugs = $this->manualRoleMapping[$application->id];
            } else {
                $roleSlugs = $cUser['roles'] ?? [];
            }

            $userQuery = User::query();
            if (! empty($cUser['nip'])) {
                $userQuery->where('nip', $cUser['nip']);
            }
            if (! empty($cUser['email'])) {
                $userQuery->orWhere('email', $cUser['email']);
            }
            $user = $userQuery->first();

            $existingProfiles = [];
            if ($user) {
                $existingProfiles = $user->accessProfiles()
                    ->whereHas('roles', function ($q) use ($application) {
                        $q->where('application_id', $application->id);
                    })
                    ->with('roles')
                    ->get()
                    ->map(function ($profile) {
                        return [
                            'id' => $profile->id,
                            'slug' => $profile->slug,
                            'name' => $profile->name,
                            'role_slugs' => $profile->roles->pluck('slug')->toArray(),
                        ];
                    })->toArray();
            }

            $plan = $assignmentService->planProfilesForRoleSlugs($application, $roleSlugs);

            $usersPreview[] = [
                'nip' => $cUser['nip'] ?? null,
                'email' => $cUser['email'] ?? null,
                'name' => $cUser['name'] ?? null,
                'active' => $cUser['active'] ?? null,
                'client_role_slugs' => $roleSlugs,
                'has_iam_user' => (bool) $user,
                'iam_user_id' => $user?->id,
                'current_profile_assignments' => $existingProfiles,
                'planned_profile_assignment' => $plan,
            ];
        }

        return [
            'success' => true,
            'message' => 'Dry-run preview generated',
            'iam_users' => $this->getIamUsers($application),
            'client_users' => $clientUsers,
            'comparison' => $comparison,
            'preview' => $usersPreview,
        ];
    }

    /**
     * Fetch users from a client application via its sync endpoint.
     */
    public function fetchClientUsers(Application $application): array
    {
        try {
            $callbackUrl = $application->callback_url;

            if (! $callbackUrl) {
                return [
                    'success' => false,
                    'error' => 'Application has no callback URL configured',
                    'iam_users' => [],
                    'client_users' => [],
                ];
            }

            $syncUrl = $this->buildSyncUrl($callbackUrl, $application->app_key);

            Log::info('Fetching users from client application', [
                'app_key' => $application->app_key,
                'sync_url' => $syncUrl,
            ]);

            // if verification is disabled we don't send any auth headers
            if (! config('iam.backchannel_verify', true)) {
                $response = Http::timeout(10)->get($syncUrl);
            } elseif (config('iam.backchannel_method', 'jwt') === 'jwt') {
                $token = app(JWTTokenService::class)->generateBackchannelToken($application);
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->get($syncUrl);
            } else {
                // legacy hmac signature on empty body.
                // Prefer global SSO secret from IAM config (iam.sso_secret).
                // Fall back to legacy sso.secret/env, then per-application secret hash.
                $secret = config('iam.sso_secret', config('sso.secret', env('SSO_SECRET', '')));
                if (empty($secret)) {
                    $secret = $application->secret;
                }

                if (empty($secret)) {
                    Log::warning('ApplicationUserSyncService backchannel cannot sign request: missing secret', [
                        'app_key' => $application->app_key,
                    ]);

                    return [
                        'success' => false,
                        'error' => 'Missing backchannel secret for application',
                        'iam_users' => $this->getIamUsers($application),
                        'client_users' => [],
                    ];
                }

                // Decode base64-encoded secrets (Laravel convention: base64:xxxxx)
                if (is_string($secret) && str_starts_with($secret, 'base64:')) {
                    $decoded = base64_decode(substr($secret, 7), true);
                    $secret = $decoded !== false ? $decoded : $secret;
                }

                $signature = hash_hmac('sha256', '', $secret);
                $header = config('sso.backchannel.signature_header', 'IAM-Signature');

                $response = Http::withHeaders([
                    $header => $signature,
                    'X-IAM-App-Key' => $application->app_key,
                ])
                    ->timeout(10)
                    ->get($syncUrl);
            }

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => "Client returned status {$response->status()}",
                    'iam_users' => $this->getIamUsers($application),
                    'client_users' => [],
                ];
            }

            $clientData = $response->json();

            // sanity check: client should echo the app_key we requested.
            if (isset($clientData['app_key']) && $clientData['app_key'] !== $application->app_key) {
                Log::warning('Sync response app_key mismatch', [
                    'expected' => $application->app_key,
                    'received' => $clientData['app_key'],
                    'sync_url' => $syncUrl,
                ]);
            }

            $clientUsers = $clientData['users'] ?? [];

            // Log all users fetched from client with their role slugs
            Log::info('client_users_fetched', [
                'app_key' => $application->app_key,
                'application_id' => $application->id,
                'total_users' => count($clientUsers),
                'users' => array_map(function ($cUser) {
                    return [
                        'nip' => $cUser['nip'] ?? null,
                        'email' => $cUser['email'] ?? null,
                        'name' => $cUser['name'] ?? null,
                        'active' => $cUser['active'] ?? true,
                        'role_slugs' => $cUser['roles'] ?? [],
                    ];
                }, $clientUsers),
            ]);

            return [
                'success' => true,
                'iam_users' => $this->getIamUsers($application),
                'client_users' => $clientUsers,
                'comparison' => $this->compareUsers($application, $clientUsers),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch client users', [
                'app_key' => $application->app_key,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'iam_users' => $this->getIamUsers($application),
                'client_users' => [],
            ];
        }
    }

    /**
     * Get all users that currently have at least one role for the application.
     */
    protected function getIamUsers(Application $application, ?int $userId = null): array
    {
        // include users who either have a direct application role (for
        // backwards compatibility) or who are connected to profiles that
        // contain roles for this application.  the `effectiveApplicationRoles`
        // helper on the model simplifies gathering the slugs.
        // WARNING: the inner queries below are *sensitive* to column
        // qualification.  both `iam_roles` and the pivot `iam_user_application_roles`
        // define an `application_id` column.  the previous implementation used a
        // plain `$q2->where('application_id', $id)` which produced ambiguous SQL
        // and caused a runtime exception in the logs.  do **not** revert this
        // block to an unqualified condition.
        $userQuery = User::query()
            ->where(function ($q) use ($application, $userId) {
                // filter by application via the roles table (direct roles or profiles)
                // plus always include a targeted user ID even if they do not yet
                // have any application roles.
                $q->whereHas('applicationRoles', function ($q2) use ($application) {
                    $q2->where('iam_roles.application_id', $application->id);
                })
                    ->orWhereHas('accessProfiles.roles', function ($q3) use ($application) {
                        $q3->where('iam_roles.application_id', $application->id);
                    });

                if ($userId !== null) {
                    $q->orWhere('id', $userId);
                }
            });

        $users = $userQuery->with(['accessProfiles.roles', 'applicationRoles'])->get();

        return $users->map(function (User $user) use ($application) {
            $roles = $user->effectiveApplicationRoles()
                ->where('application_id', $application->id)
                ->pluck('name')
                ->toArray();

            return [
                'id' => $user->id,
                'nip' => $user->nip,
                'email' => $user->email,
                'name' => $user->name,
                'active' => $user->active,
                'unit_kerja' => $user->unitKerjas()->pluck('unit_name')->toArray(),
                'roles' => $roles,
            ];
        })->toArray();
    }

    /**
     * Push IAM users to the client application (push mode).
     */
    protected function pushUsersToClient(Application $application, ?int $userId = null): array
    {
        $users = $this->getIamUsers($application, $userId);
        $syncUrl = $this->buildPushUsersUrl($application, $application->app_key);

        Log::info('iam.push_users_request', [
            'app_key' => $application->app_key,
            'application_id' => $application->id,
            'sync_url' => $syncUrl,
            'user_count' => count($users),
            'pushed_user_ids' => collect($users)->pluck('id')->toArray(),
            'user_id_target' => $userId,
        ]);

        try {
            $payload = ['users' => $users];
            $jsonBody = json_encode($payload);

            if (! config('iam.backchannel_verify', true)) {
                $response = Http::timeout(50)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->withBody($jsonBody, 'application/json')
                    ->post($syncUrl);
            } elseif (config('iam.backchannel_method', 'jwt') === 'jwt') {
                $token = app(JWTTokenService::class)->generateBackchannelToken($application);
                $response = Http::withToken($token)
                    ->timeout(50)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->withBody($jsonBody, 'application/json')
                    ->post($syncUrl);
            } else {
                $secret = config('iam.sso_secret', config('sso.secret', env('SSO_SECRET', ''))) ?: $application->secret;

                // Decode base64-encoded secrets (Laravel convention: base64:xxxxx)
                if (is_string($secret) && str_starts_with($secret, 'base64:')) {
                    $decoded = base64_decode(substr($secret, 7), true);
                    $secret = $decoded !== false ? $decoded : $secret;
                }

                $signature = hash_hmac('sha256', $jsonBody, $secret);
                $header = config('sso.backchannel.signature_header', 'IAM-Signature');

                $response = Http::withHeaders([
                    $header => $signature,
                    'X-IAM-App-Key' => $application->app_key,
                    'Content-Type' => 'application/json',
                ])
                    ->timeout(50)
                    ->withBody($jsonBody, 'application/json')
                    ->post($syncUrl);
            }

            if (! $response->successful()) {
                Log::warning('iam.push_users_failed', [
                    'app_key' => $application->app_key,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'hint' => 'Pastikan client IAM_USER_SYNC_MODE=push dan endpoint /api/iam/push-users dapat dipanggil.',
                ]);

                return [
                    'success' => false,
                    'error' => "Client returned status {$response->status()}",
                    'iam_users' => $users,
                    'client_users' => [],
                ];
            }

            $clientData = $response->json() ?? [];

            Log::info('iam.push_users_response', [
                'app_key' => $application->app_key,
                'status' => $response->status(),
                'response' => $clientData,
            ]);

            return array_merge([
                'success' => true,
                'message' => 'Push completed',
                'iam_users' => $users,
            ], $clientData);
        } catch (\Exception $e) {
            Log::error('Failed to push users to client application', [
                'app_key' => $application->app_key,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'iam_users' => $users,
                'client_users' => [],
            ];
        }
    }

    /**
     * Build the client endpoint to receive pushed users.
     */
    protected function buildPushUsersUrl(Application $application, string $appKey): string
    {
        $base = $this->getBackchannelUrl($application);

        if (! $base) {
            throw new \InvalidArgumentException('Application has no callback/backchannel URL configured for sync.');
        }

        return $base . '/api/iam/push-users?app_key=' . urlencode($appKey);
    }

    /**
     * Determine the base URL for back-channel operations (sync users/roles).
     */
    protected function getBackchannelUrl(Application $application): ?string
    {
        $backchannel = $application->backchannel_url ?: $application->callback_url;

        if (! $backchannel) {
            return null;
        }

        $parsed = parse_url($backchannel);

        if (empty($parsed['scheme']) || empty($parsed['host'])) {
            return null;
        }

        $base = $parsed['scheme'] . '://' . $parsed['host'];

        if (! empty($parsed['port'])) {
            $base .= ':' . $parsed['port'];
        }

        return rtrim($base, '/');
    }

    /**
     * Compare IAM users with client users by NIP (or email).  We only look at
     * existence, not roles – role differences are handled during assignment.
     */
    protected function compareUsers(Application $application, array $clientUsers): array
    {
        $iamUsers = $this->getIamUsers($application);

        $iamKeys = collect($iamUsers)
            ->mapWithKeys(fn($u) => [($u['nip'] ?? $u['email'] ?? '') => true])
            ->toArray();
        $clientKeys = collect($clientUsers)
            ->mapWithKeys(fn($u) => [($u['nip'] ?? $u['email'] ?? '') => true])
            ->toArray();

        return [
            'in_sync' => collect($iamUsers)
                ->filter(fn($u) => isset($clientKeys[$u['nip'] ?? $u['email']]))
                ->values()
                ->toArray(),
            'missing_in_client' => collect($iamUsers)
                ->filter(fn($u) => ! isset($clientKeys[$u['nip'] ?? $u['email']]))
                ->values()
                ->toArray(),
            'extra_in_client' => collect($clientUsers)
                ->filter(fn($u) => ! isset($iamKeys[$u['nip'] ?? $u['email']]))
                ->values()
                ->toArray(),
        ];
    }

    /**
     * Build sync URL from callback URL.
     */
    protected function buildSyncUrl(string $callbackUrl, string $appKey): string
    {
        $parsed = parse_url($callbackUrl);
        $domain = $parsed['scheme'] . '://' . $parsed['host'];

        if (isset($parsed['port'])) {
            $domain .= ':' . $parsed['port'];
        }

        return $domain . '/api/iam/sync-users?app_key=' . urlencode($appKey);
    }
}
