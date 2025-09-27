<?php

namespace App\Http\Middleware;

use App\Services\Contracts\AppRegistryContract;
use App\Services\Contracts\CacheResolverContract;
use App\Services\Contracts\RbacServiceContract;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppPermission
{
    public function __construct(
        private readonly AppRegistryContract $appRegistry,
        private readonly CacheResolverContract $cacheResolver,
        private readonly RbacServiceContract $rbacService
    ) {
    }

    public function handle(Request $request, Closure $next, string $appKey, string $permission): Response
    {
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        $application = $this->appRegistry->getByKeyOrFail($appKey);

        if ($this->hasPermissionViaClaims($request, $permission)) {
            return $next($request);
        }

        $cached = $this->cacheResolver->rememberUserPerms($user, $application);

        if (in_array($permission, $cached, true)) {
            return $next($request);
        }

        if ($this->rbacService->can($user, $application, $permission)) {
            $this->cacheResolver->rememberUserPerms($user, $application);

            return $next($request);
        }

        abort(403, 'Unauthorized for application permission.');
    }

    protected function hasPermissionViaClaims(Request $request, string $permission): bool
    {
        $claims = $request->attributes->get('rbac_claims');

        if (is_array($claims) && in_array($permission, $claims['perms'] ?? [], true)) {
            return true;
        }

        $claims = $request->attributes->get('oauth_claims');

        if (is_array($claims) && in_array($permission, $claims['perms'] ?? [], true)) {
            return true;
        }

        $user = $request->user();

        if ($user && method_exists($user, 'token')) {
            $token = $user->token();

            if ($token && method_exists($token, 'can') && $token->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
