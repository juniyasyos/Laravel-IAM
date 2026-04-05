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

        $requestedApplications = collect($extraApplications)
            ->map(function ($app) {
                if ($app instanceof Application) {
                    return $app;
                }

                if (is_numeric($app)) {
                    return Application::find((int) $app);
                }

                if (is_string($app)) {
                    return Application::where('app_key', $app)->first();
                }

                return null;
            })
            ->filter()
            ->values();

        Log::info('iam.backchannel_logout_evaluate', [
            'user_id' => $user->id,
            'direct_application_keys' => $directApplications->pluck('app_key')->values()->all(),
            'profile_application_keys' => $profileApplications->pluck('app_key')->values()->all(),
            'requested_application_keys' => $requestedApplications->pluck('app_key')->values()->all(),
        ]);

        $applications = $directApplications
            ->merge($profileApplications)
            ->merge($requestedApplications)
            ->unique('id')
            ->filter(function (Application $application) use ($user, $forceLogout) {
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

                $hasDirectRole = $user->applicationRoles()
                    ->where('iam_roles.application_id', $application->id)
                    ->exists();

                $hasProfileAccess = $user->hasActiveAccessProfileForApp($application);

                $reason = $hasDirectRole ? 'direct_role_exists' : ($hasProfileAccess ? 'profile_access_exists' : 'no_access');
                Log::info('iam.backchannel_logout_app_decision', [
                    'user_id' => $user->id,
                    'application_id' => $application->id,
                    'app_key' => $application->app_key,
                    'has_direct_role' => $hasDirectRole,
                    'has_profile_access' => $hasProfileAccess,
                    'decision' => $reason,
                ]);

                return ! ($hasDirectRole || $hasProfileAccess);
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
