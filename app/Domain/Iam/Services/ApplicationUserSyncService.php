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

    public function __construct(array $allowedProfileIds = [])
    {
        $this->allowedProfileIds = $allowedProfileIds;
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
    public function syncUsers(Application $application): array
    {
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
            // find by nip first, fallback to email if provided
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
                    // password is meaningless for sync; generate a random hash
                    'password' => bcrypt(Str::random(16)),
                    'active' => $cUser['active'] ?? true,
                ]);
                $created++;
            } else {
                $user->update([
                    'name' => $cUser['name'] ?? $user->name,
                    'email' => $cUser['email'] ?? $user->email,
                    'active' => $cUser['active'] ?? $user->active,
                ]);
                $updated++;
            }

            // roles array expected on client side; default to empty
            $roleSlugs = $cUser['roles'] ?? [];
            try {
                // the new workflow no longer assigns roles directly; instead we
                // link the user to the appropriate access profiles that contain
                // the given slugs for this application.
                $assignmentService->syncProfilesForUserAndApp($user, $application, $roleSlugs);
            } catch (\Exception $e) {
                // log and continue, but don't fail the entire sync
                Log::warning('user_role_sync_failed', [
                    'application_id' => $application->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'roles' => $roleSlugs,
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
                // legacy hmac signature on empty body
                $secret = config('sso.secret', env('SSO_SECRET', ''));
                $signature = hash_hmac('sha256', '', $secret);
                $header = config('sso.backchannel.signature_header', 'IAM-Signature');

                $response = Http::withHeaders([$header => $signature])
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
    protected function getIamUsers(Application $application): array
    {
        // include users who either have a direct application role (for
        // backwards compatibility) or who are connected to profiles that
        // contain roles for this application.  the `effectiveApplicationRoles`
        // helper on the model simplifies gathering the slugs.
        $users = User::query()
            ->where(function ($q) use ($application) {
                $q->whereHas('applicationRoles', function ($q2) use ($application) {
                    $q2->where('application_id', $application->id);
                })
                    ->orWhereHas('accessProfiles.roles', function ($q3) use ($application) {
                        $q3->where('application_id', $application->id);
                    });
            })
            ->with(['accessProfiles.roles', 'applicationRoles'])
            ->get();

        return $users->map(function (User $user) use ($application) {
            $roles = $user->effectiveApplicationRoles()
                ->where('application_id', $application->id)
                ->pluck('slug')
                ->toArray();

            return [
                'id' => $user->id,
                'nip' => $user->nip,
                'email' => $user->email,
                'name' => $user->name,
                'active' => $user->active,
                'roles' => $roles,
            ];
        })->toArray();
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
