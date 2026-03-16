<?php

namespace App\Domain\Iam\Services;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\JWTTokenService;

class ApplicationRoleSyncService
{
    /**
     * Sync roles from client application - fetch and save to database.
     */
    public function syncRoles(Application $application): array
    {
        $result = $this->fetchClientRoles($application);

        if (!$result['success']) {
            return $result;
        }

        $clientRoles = $result['client_roles'];
        $comparison = $result['comparison'];

        // Create roles that exist in client but not in IAM
        $created = 0;
        foreach ($comparison['extra_in_client'] as $clientRole) {
            ApplicationRole::create([
                'application_id' => $application->id,
                'slug' => $clientRole['slug'],
                'name' => $clientRole['name'],
                'description' => $clientRole['description'],
                'is_system' => $clientRole['is_system'] ?? false,
            ]);
            $created++;
        }

        // Update existing roles with new data from client
        $updated = 0;
        foreach ($comparison['in_sync'] as $clientRole) {
            $iamRole = ApplicationRole::where('application_id', $application->id)
                ->where('slug', $clientRole['slug'])
                ->first();

            if ($iamRole) {
                $iamRole->update([
                    'name' => $clientRole['name'],
                    'description' => $clientRole['description'],
                    'is_system' => $clientRole['is_system'] ?? false,
                ]);
                $updated++;
            }
        }

        // Note: We don't delete roles that exist in IAM but not in client
        // to avoid accidentally removing roles that might be needed

        return [
            'success' => true,
            'message' => "Sync completed: {$created} roles created, {$updated} roles updated",
            'created' => $created,
            'updated' => $updated,
            'iam_roles' => $this->getIamRoles($application),
            'client_roles' => $clientRoles,
            'comparison' => $this->compareRoles($application, $clientRoles),
        ];
    }

    /**
     * Fetch roles from a client application via its sync endpoint.
     */
    public function fetchClientRoles(Application $application): array
    {
        try {
            $callbackUrl = $application->callback_url;

            if (!$callbackUrl) {
                return [
                    'success' => false,
                    'error' => 'Application has no callback URL configured',
                    'iam_roles' => [],
                    'client_roles' => [],
                ];
            }

            // Build the sync endpoint from callback URL
            // From: https://example.com/callback
            // To: https://example.com/api/iam/sync-roles?app_key=siimut
            $syncUrl = $this->buildSyncUrl($callbackUrl, $application->app_key);

            Log::info('Fetching roles from client application', [
                'app_key' => $application->app_key,
                'sync_url' => $syncUrl,
            ]);

            // if verification is disabled we don't send any authentication
            if (! config('iam.backchannel_verify', true)) {
                $response = Http::timeout(10)->get($syncUrl);
            } elseif (config('iam.backchannel_method', 'jwt') === 'jwt') {
                $token = app(JWTTokenService::class)->generateBackchannelToken($application);
                $response = Http::withToken($token)
                    ->timeout(50)
                    ->get($syncUrl);
            } else {
                $secret = config('sso.secret', env('SSO_SECRET', ''));
                $signature = hash_hmac('sha256', '', $secret);
                $header = config('sso.backchannel.signature_header', 'IAM-Signature');

                $response = Http::withHeaders([$header => $signature])
                    ->timeout(50)
                    ->get($syncUrl);
            }

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => "Client returned status {$response->status()}",
                    'iam_roles' => $this->getIamRoles($application),
                    'client_roles' => [],
                ];
            }

            $clientData = $response->json();
            $clientRoles = $clientData['roles'] ?? [];

            return [
                'success' => true,
                'iam_roles' => $this->getIamRoles($application),
                'client_roles' => $clientRoles,
                'comparison' => $this->compareRoles($application, $clientRoles),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch client roles', [
                'app_key' => $application->app_key,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'iam_roles' => $this->getIamRoles($application),
                'client_roles' => [],
            ];
        }
    }

    /**
     * Get all roles defined in IAM for this application.
     */
    protected function getIamRoles(Application $application): array
    {
        return $application->roles()
            ->get()
            ->map(fn($role) => [
                'id' => $role->id,
                'slug' => $role->slug,
                'name' => $role->name,
                'description' => $role->description,
                'is_system' => $role->is_system,
            ])
            ->toArray();
    }

    /**
     * Compare IAM roles with client roles.
     */
    protected function compareRoles(Application $application, array $clientRoles): array
    {
        $iamRoles = $this->getIamRoles($application);

        $iamSlugs = collect($iamRoles)->pluck('slug')->flip()->toArray();
        $clientSlugs = collect($clientRoles)->pluck('slug')->flip()->toArray();

        return [
            'in_sync' => collect($iamRoles)
                ->filter(fn($role) => isset($clientSlugs[$role['slug']]))
                ->values()
                ->toArray(),
            'missing_in_client' => collect($iamRoles)
                ->filter(fn($role) => !isset($clientSlugs[$role['slug']]))
                ->values()
                ->toArray(),
            'extra_in_client' => collect($clientRoles)
                ->filter(fn($role) => !isset($iamSlugs[$role['slug']]))
                ->values()
                ->toArray(),
        ];
    }

    /**
     * Build sync URL from callback URL.
     */
    protected function buildSyncUrl(string $callbackUrl, string $appKey): string
    {
        // Parse the callback URL to get the domain
        $parsed = parse_url($callbackUrl);
        $domain = $parsed['scheme'] . '://' . $parsed['host'];

        if (isset($parsed['port'])) {
            $domain .= ':' . $parsed['port'];
        }

        return $domain . '/api/iam/sync-roles?app_key=' . urlencode($appKey);
    }
}
