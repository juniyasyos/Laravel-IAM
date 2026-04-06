<?php

namespace App\Domain\Iam\Services;

use App\Domain\Iam\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\JWTTokenService;

class BackchannelLogoutService
{
    public function notifyUser(User $user, array $extraApplications = [], bool $forceLogout = false): array
    {
        $responses = [];

        $directApplications = $user->applicationRoles()->with('application')->get()
            ->pluck('application')
            ->filter();

        $profileApplications = $user->accessProfiles()
            ->with(['roles.application'])
            ->get()
            ->flatMap(fn($profile) => $profile->roles->pluck('application'))
            ->filter();

        // OPTIMIZATION: Pre-fetch all applications at once instead of per-item lookup
        $requestedApplications = collect($extraApplications)
            ->partition(function ($app) {
                return $app instanceof Application;
            });

        $alreadyApplications = $requestedApplications[0];
        $lookupArray = $requestedApplications[1];

        // Separate numeric IDs and string keys for efficient batch lookup
        $ids = $lookupArray->filter(fn($v) => is_numeric($v))->values()->all();
        $keys = $lookupArray->filter(fn($v) => is_string($v))->values()->all();

        // OPTIMIZATION: Single batch query instead of per-item queries
        $lookedupApps = collect();
        if (!empty($ids)) {
            $lookedupApps = $lookedupApps->merge(
                Application::whereIn('id', $ids)->get()
            );
        }
        if (!empty($keys)) {
            $lookedupApps = $lookedupApps->merge(
                Application::whereIn('app_key', $keys)->get()
            );
        }

        $requestedApplications = $alreadyApplications
            ->merge($lookedupApps)
            ->filter()
            ->values();

        Log::info('iam.backchannel_logout_evaluate', [
            'user_id' => $user->id,
            'direct_application_keys' => $directApplications->pluck('app_key')->values()->all(),
            'profile_application_keys' => $profileApplications->pluck('app_key')->values()->all(),
            'requested_application_keys' => $requestedApplications->pluck('app_key')->values()->all(),
        ]);

        // OPTIMIZATION: Pre-compute which apps the user has access to (avoid per-app queries in filter)
        $userAppsWithDirectRoles = $user->applicationRoles()
            ->select('iam_roles.application_id')
            ->distinct()
            ->pluck('application_id')
            ->toArray();

        $userAppsWithProfileAccess = $user->accessProfiles()
            ->where('is_active', true)
            ->with(['roles:application_id'])
            ->get()
            ->flatMap(fn($p) => $p->roles->pluck('application_id'))
            ->unique()
            ->toArray();

        $userAccessibleAppIds = array_unique(array_merge($userAppsWithDirectRoles, $userAppsWithProfileAccess));

        $applications = $directApplications
            ->merge($profileApplications)
            ->merge($requestedApplications)
            ->unique('id')
            ->filter(function (Application $application) use ($user, $forceLogout, $userAccessibleAppIds) {
                if ($forceLogout) {
                    Log::info('iam.backchannel_logout_app_decision', [
                        'user_id' => $user->id,
                        'application_id' => $application->id,
                        'app_key' => $application->app_key,
                        'force_logout' => true,
                        'decision' => 'forced',
                    ]);

                    return true;
                }

                // OPTIMIZATION: Check in pre-computed memory array instead of querying
                $hasAccess = in_array($application->id, $userAccessibleAppIds);
                $reason = $hasAccess ? 'has_access' : 'no_access';

                Log::info('iam.backchannel_logout_app_decision', [
                    'user_id' => $user->id,
                    'application_id' => $application->id,
                    'app_key' => $application->app_key,
                    'has_access' => $hasAccess,
                    'decision' => $reason,
                ]);

                return ! $hasAccess;
            });

        if ($applications->isEmpty()) {
            Log::info('iam.backchannel_logout_nobody_to_notify', [
                'user_id' => $user->id,
            ]);
        }

        foreach ($applications as $application) {
            /** @var Application $application */
            $logoutUri = $application->backchannel_logout_uri;
            if (! $logoutUri) {
                Log::info('iam.backchannel_logout_skipped_no_uri', [
                    'app_key' => $application->app_key,
                    'user_id' => $user->id,
                ]);
                continue;
            }

            $payload = [
                'event' => 'logout',
                'user' => [
                    'id' => $user->id,
                    'nip' => $user->nip ?? null,
                    'email' => $user->email ?? null,
                ],
            ];

            try {
                $client = Http::timeout(50)->withHeaders(['Content-Type' => 'application/json']);

                if (! config('iam.backchannel_verify', true)) {
                    // no auth
                } elseif (config('iam.backchannel_method', 'jwt') === 'jwt') {
                    $token = app(JWTTokenService::class)->generateBackchannelToken($application);
                    $client = $client->withToken($token);
                } else {
                    $secret = config('iam.sso_secret', config('sso.secret', env('SSO_SECRET', ''))) ?: $application->secret;

                    // Decode base64-encoded secrets (Laravel convention: base64:xxxxx)
                    if (is_string($secret) && str_starts_with($secret, 'base64:')) {
                        $decoded = base64_decode(substr($secret, 7), true);
                        $secret = $decoded !== false ? $decoded : $secret;
                    }

                    $signature = hash_hmac('sha256', json_encode($payload), $secret);
                    $header = config('sso.backchannel.signature_header', 'IAM-Signature');
                    $client = $client->withHeaders([$header => $signature]);
                }

                $response = $client->post($logoutUri, $payload);

                $responses[] = [
                    'app_key' => $application->app_key,
                    'logout_uri' => $logoutUri,
                    'status' => $response->status(),
                    'success' => $response->successful(),
                ];

                if (! $response->successful()) {
                    Log::warning('iam.backchannel_logout_failed', [
                        'app_key' => $application->app_key,
                        'user_id' => $user->id,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                } else {
                    Log::info('iam.backchannel_logout_sent', [
                        'app_key' => $application->app_key,
                        'user_id' => $user->id,
                        'status' => $response->status(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('iam.backchannel_logout_exception', [
                    'app_key' => $application->app_key,
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                ]);

                $responses[] = [
                    'app_key' => $application->app_key,
                    'logout_uri' => $logoutUri,
                    'status' => null,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        Log::info('iam.backchannel_logout_summary', [
            'user_id' => $user->id,
            'notified_app_keys' => collect($responses)->pluck('app_key')->values()->all(),
            'num_notified' => count($responses),
        ]);

        return $responses;
    }
}
