<?php

namespace App\Passport;

use App\Models\User;
use App\Services\Contracts\ClaimsBuilderContract;
use DateTimeImmutable;
use Laravel\Passport\Bridge\AccessToken as PassportAccessToken;

class AccessToken extends PassportAccessToken
{
    protected ?array $rbacClaims = null;

    public function __toString()
    {
        $this->initJwtConfiguration();

        $builder = $this->jwtConfiguration->builder()
            ->permittedFor($this->getClient()->getIdentifier())
            ->identifiedBy($this->getIdentifier())
            ->issuedAt(new DateTimeImmutable())
            ->canOnlyBeUsedAfter(new DateTimeImmutable())
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo((string) $this->getUserIdentifier())
            ->withClaim('scopes', $this->formatScopes());

        foreach ($this->rbacClaims() as $key => $value) {
            $builder->withClaim($key, $value);
        }

        return $builder
            ->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey())
            ->toString();
    }

    protected function formatScopes(): array
    {
        return array_map(static fn ($scope) => $scope->getIdentifier(), $this->getScopes());
    }

    protected function rbacClaims(): array
    {
        if ($this->rbacClaims !== null) {
            return $this->rbacClaims;
        }

        $userId = $this->getUserIdentifier();

        if (! $userId) {
            return $this->rbacClaims = [];
        }

        $user = User::query()->find($userId);

        if (! $user) {
            return $this->rbacClaims = [];
        }

        /** @var ClaimsBuilderContract $builder */
        $builder = app(ClaimsBuilderContract::class);

        return $this->rbacClaims = $builder->build($user);
    }
}
