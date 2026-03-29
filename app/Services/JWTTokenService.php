<?php

namespace App\Services;

use  App\Domain\Iam\Models\Application;;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;

class JWTTokenService
{
    private string $secretKey;
    private string $algorithm = 'HS256';
    private string $issuer;

    public function __construct()
    {
        $this->secretKey = config('app.key');
        $this->issuer = config('app.url');
    }

    /**
     * Generate an access token for the user and application.
     *
     * @param  User  $user
     * @param  Application  $application
     * @return string
     */
    public function generateAccessToken(User $user, Application $application): string
    {
        $now = time();
        $expiry = $now + $application->getTokenExpirySeconds();

        $roles = $this->getUserRolesForApplication($user, $application);

        if (empty($roles)) {
            throw new \Exception('Access denied: no roles available for this user and application.');
        }

        $payload = [
            'iss' => $this->issuer,
            'sub' => $user->id,
            'iat' => $now,
            'exp' => $expiry,
            'name' => $user->name,
            'email' => $user->email,
            'app_key' => $application->app_key,
            'roles' => $roles,
            'type' => 'access',
        ];

        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }

    /**
     * Generate a short-lived token suitable for back‑channel requests such
     * as role/user synchronisation.  The token is deliberately sparse and
     * is only used by other services to prove the request originated from
     * the IAM server.
     */
    public function generateBackchannelToken(Application $application): string
    {
        $now = time();
        $expiry = $now + 300; // five minutes

        $payload = [
            'iss' => $this->issuer,
            'iat' => $now,
            'exp' => $expiry,
            'app_key' => $application->app_key,
            'type' => 'backchannel',
        ];

        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }

    /**
     * Generate a refresh token for the user and application.
     *
     * @param  User  $user
     * @param  Application  $application
     * @return string
     */
    public function generateRefreshToken(User $user, Application $application): string
    {
        $now = time();
        $expiry = $now + (86400 * 30); // 30 days

        $payload = [
            'iss' => $this->issuer,
            'sub' => $user->id,
            'iat' => $now,
            'exp' => $expiry,
            'app_key' => $application->app_key,
            'type' => 'refresh',
        ];

        $token = JWT::encode($payload, $this->secretKey, $this->algorithm);

        // Store refresh token in cache for revocation capability
        Cache::put(
            "refresh_token:{$user->id}:{$application->app_key}",
            $token,
            $expiry - $now
        );

        return $token;
    }

    /**
     * Verify and decode a token.
     *
     * @param  string  $token
     * @return object
     *
     * @throws \Exception
     */
    public function verifyToken(string $token): object
    {
        try {
            return JWT::decode($token, new Key($this->secretKey, $this->algorithm));
        } catch (\Exception $e) {
            throw new \Exception('Invalid or expired token: ' . $e->getMessage());
        }
    }

    /**
     * Validate if token is for specific application.
     *
     * @param  string  $token
     * @param  string  $appKey
     * @return bool
     */
    public function validateTokenForApp(string $token, string $appKey): bool
    {
        try {
            $decoded = $this->verifyToken($token);

            return isset($decoded->app_key) && $decoded->app_key === $appKey;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Revoke refresh token.
     *
     * @param  int  $userId
     * @param  string  $appKey
     * @return void
     */
    public function revokeRefreshToken(int $userId, string $appKey): void
    {
        Cache::forget("refresh_token:{$userId}:{$appKey}");
    }

    /**
     * Check if refresh token is revoked.
     *
     * @param  object  $decoded
     * @return bool
     */
    public function isRefreshTokenRevoked(object $decoded): bool
    {
        if (! isset($decoded->sub, $decoded->app_key, $decoded->type) || $decoded->type !== 'refresh') {
            return true;
        }

        $cachedToken = Cache::get("refresh_token:{$decoded->sub}:{$decoded->app_key}");

        return $cachedToken === null;
    }

    /**
     * Get user IAM roles for specific application.
     *
     * @param  User  $user
     * @param  Application  $application
     * @return array
     */
    private function getUserRolesForApplication(User $user, Application $application): array
    {
        // Get effective roles (direct + via access profiles) for this application
        $roles = $user->effectiveApplicationRoles()
            ->with('application')
            ->whereHas('application', function ($query) use ($application) {
                $query->where('id', $application->id);
            })
            ->get();

        return $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'slug' => $role->slug,
                'name' => $role->name,
                'is_system' => $role->is_system,
                'description' => $role->description,
            ];
        })->toArray();
    }
}
