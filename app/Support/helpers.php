<?php

use App\Services\Contracts\AppRegistryContract;
use App\Services\Contracts\RbacServiceContract;
use App\Services\Contracts\CacheResolverContract;
use App\Models\User;

if (! function_exists('canApp')) {
    function canApp(User $user, string $appKey, string $permission): bool
    {
        /** @var AppRegistryContract $registry */
        $registry = app(AppRegistryContract::class);
        /** @var RbacServiceContract $rbac */
        $rbac = app(RbacServiceContract::class);
        /** @var CacheResolverContract $cache */
        $cache = app(CacheResolverContract::class);

        $application = $registry->getByKeyOrFail($appKey);
        $permissions = $cache->rememberUserPerms($user, $application);

        if (in_array($permission, $permissions, true)) {
            return true;
        }

        return $rbac->can($user, $application, $permission);
    }
}
