<?php

namespace App\Domain\Iam\Services;

use App\Domain\Iam\DataTransferObjects\TokenClaims;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;

class TokenBuilder
{
    private string $secretKey;
    private string $algorithm = 'HS256';

    public function __construct(
        private readonly UserRoleAssignmentService $userRoleService
    ) {
        $this->secretKey = config('iam.signing_key') ?? config('app.key');
    }

    /**
     * Build token claims for a user.
     *
     * @param  array<string, mixed>  $extra  Additional custom claims
     */
    public function buildClaimsForUser(User $user, array $extra = []): TokenClaims
    {
        $apps = $this->userRoleService->getAppsForUser($user);
        $rolesByApp = $this->userRoleService->getRolesByAppForUser($user);

        $now = time();
        $ttl = config('iam.token_ttl', 3600);

        return new TokenClaims(
            userId: $user->id,
            nip: $user->nip,
            email: $user->email,
            name: $user->name,
            apps: $apps,
            rolesByApp: $rolesByApp,
            issuer: config('iam.issuer', config('app.url')),
            issuedAt: $now,
            expiresAt: $now + $ttl,
            unit: $user->unit,
            employeeId: $user->employee_id ?? null,
            extra: $extra
        );
    }

    /**
     * Encode token claims into a JWT string.
     */
    public function encode(TokenClaims $claims): string
    {
        return JWT::encode($claims->toPayload(), $this->secretKey, $this->algorithm);
    }

    /**
     * Build and encode a token for a user in one step.
     *
     * @param  array<string, mixed>  $extra
     */
    public function buildTokenForUser(User $user, array $extra = []): string
    {
        $claims = $this->buildClaimsForUser($user, $extra);

        return $this->encode($claims);
    }

    /**
     * Decode and verify a JWT token.
     *
     * @throws \Exception
     */
    public function decode(string $token): TokenClaims
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            $payload = (array) $decoded;

            // Convert roles_by_app from stdClass to array if needed
            if (isset($payload['roles_by_app']) && $payload['roles_by_app'] instanceof \stdClass) {
                $payload['roles_by_app'] = (array) $payload['roles_by_app'];
            }

            return TokenClaims::fromArray($payload);
        } catch (\Exception $e) {
            throw new \Exception('Invalid or expired token: ' . $e->getMessage());
        }
    }

    /**
     * Verify token and return claims.
     *
     * @throws \Exception
     */
    public function verify(string $token): TokenClaims
    {
        $claims = $this->decode($token);

        if ($claims->isExpired()) {
            throw new \Exception('Token has expired.');
        }

        // Reject tokens issued before a recorded user logout timestamp.
        $logoutAt = Cache::get("user_logout_at:{$claims->userId}");

        if ($logoutAt !== null && $claims->issuedAt <= $logoutAt) {
            throw new \Exception('Token has been revoked due to user logout.');
        }

        return $claims;
    }

    /**
     * Check if token is valid without throwing exceptions.
     */
    public function isValid(string $token): bool
    {
        try {
            $this->verify($token);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Refresh a token (decode old token, issue new one with updated expiry).
     *
     * @throws \Exception
     */
    public function refresh(string $token): string
    {
        $oldClaims = $this->decode($token);

        // Find user
        $user = User::findOrFail($oldClaims->userId);

        // Build fresh token
        return $this->buildTokenForUser($user, $oldClaims->extra);
    }
}
