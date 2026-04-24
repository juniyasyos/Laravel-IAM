<?php

namespace App\Domain\Iam\Services;

use App\Domain\Iam\Models\Application;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\JWTTokenService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnitKerjaPushService
{
    public function push(Application $application, ?int $unitKerjaId = null): array
    {
        $payload = $this->buildPayload($unitKerjaId);
        $pushUrl = $this->buildPushUrl($application, $application->app_key);

        Log::info('iam.push_unit_kerja_request', [
            'app_key' => $application->app_key,
            'application_id' => $application->id,
            'push_url' => $pushUrl,
            'unit_kerja_id' => $unitKerjaId,
            'unit_count' => count($payload['data']['units']),
            'user_count' => count($payload['data']['users']),
            'relation_count' => count($payload['data']['user_unit_kerja']),
        ]);

        $jsonBody = json_encode($payload);

        try {
            if (! config('iam.backchannel_verify', true)) {
                $response = Http::timeout(50)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->withBody($jsonBody, 'application/json')
                    ->post($pushUrl);
            } elseif (config('iam.backchannel_method', 'jwt') === 'jwt') {
                $token = app(JWTTokenService::class)->generateBackchannelToken($application);
                $response = Http::withToken($token)
                    ->timeout(50)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->withBody($jsonBody, 'application/json')
                    ->post($pushUrl);
            } else {
                $secret = $this->resolveBackchannelSecret($application);
                $signature = hash_hmac('sha256', $jsonBody, $secret);
                $header = config('sso.backchannel.signature_header', 'IAM-Signature');

                $response = Http::withHeaders([
                    $header => $signature,
                    'X-IAM-App-Key' => $application->app_key,
                    'Content-Type' => 'application/json',
                ])
                    ->timeout(50)
                    ->withBody($jsonBody, 'application/json')
                    ->post($pushUrl);
            }

            if (! $response->successful()) {
                Log::warning('iam.push_unit_kerja_failed', [
                    'app_key' => $application->app_key,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => "Client returned status {$response->status()}",
                    'response' => $response->body(),
                ];
            }

            $responseData = $response->json() ?? [];

            Log::info('iam.push_unit_kerja_response', [
                'app_key' => $application->app_key,
                'status' => $response->status(),
                'response' => $responseData,
            ]);

            return array_merge(['success' => true, 'message' => 'Push Unit Kerja completed.'], $responseData);
        } catch (\Exception $e) {
            Log::error('iam.push_unit_kerja_exception', [
                'app_key' => $application->app_key,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function buildPayload(?int $unitKerjaId = null): array
    {
        $unitsQuery = UnitKerja::query()->whereNull('deleted_at');
        $relationsQuery = \Illuminate\Support\Facades\DB::table('user_unit_kerja')
            ->join('users', 'user_unit_kerja.user_id', '=', 'users.id')
            ->join('unit_kerja', 'user_unit_kerja.unit_kerja_id', '=', 'unit_kerja.id');

        if ($unitKerjaId !== null) {
            $unitsQuery->where('id', $unitKerjaId);
            $relationsQuery->where('user_unit_kerja.unit_kerja_id', $unitKerjaId);
        }

        $units = $unitsQuery->get(['id', 'unit_name', 'description', 'slug', 'created_at', 'updated_at'])->toArray();

        $userIds = $relationsQuery->pluck('user_id')->unique()->toArray();

        $users = User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'nip', 'email', 'name', 'status', 'iam_id', 'created_at', 'updated_at'])
            ->toArray();

        $relations = $relationsQuery
            ->select(
                'user_unit_kerja.user_id',
                'user_unit_kerja.unit_kerja_id',
                'user_unit_kerja.created_at as attached_at',
                'user_unit_kerja.updated_at as attached_updated_at',
                'users.nip as user_nip',
                'users.email as user_email',
                'unit_kerja.slug as unit_slug'
            )
            ->get()
            ->toArray();

        return [
            'data' => [
                'units' => $units,
                'users' => $users,
                'user_unit_kerja' => $relations,
            ],
        ];
    }

    protected function buildPushUrl(Application $application, string $appKey): string
    {
        $base = $this->getBackchannelUrl($application);

        if (! $base) {
            throw new \InvalidArgumentException('Application has no callback/backchannel URL configured for unit kerja push.');
        }

        return $base . '/api/manage-unit-kerja/client/push?app_key=' . urlencode($appKey);
    }

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

    protected function resolveBackchannelSecret(Application $application): string
    {
        $secret = config('iam.sso_secret', config('sso.secret', env('SSO_SECRET', ''))) ?: $application->secret;

        if (is_string($secret) && str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);
            $secret = $decoded !== false ? $decoded : $secret;
        }

        return $secret;
    }
}
