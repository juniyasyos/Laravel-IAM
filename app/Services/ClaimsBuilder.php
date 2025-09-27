<?php

namespace App\Services;

use App\Models\Application;
use App\Models\User;
use App\Services\Contracts\AppRegistryContract;
use App\Services\Contracts\CacheResolverContract;
use App\Services\Contracts\ClaimsBuilderContract;
use App\Services\Contracts\RbacServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ClaimsBuilder implements ClaimsBuilderContract
{
    public function __construct(
        private readonly RbacServiceContract $rbacService,
        private readonly CacheResolverContract $cacheResolver,
        private readonly AppRegistryContract $appRegistry
    ) {
    }

    public function build(User $user, ?Application $application = null): array
    {
        $applications = $application ? collect([$application]) : $this->resolveApplicationsFor($user);

        $apps = [];
        $roles = [];
        $perms = [];

        foreach ($applications as $app) {
            $apps[] = $app->app_key;
            $roles = array_merge($roles, $this->rbacService->userRoles($user, $app));
            $perms = array_merge($perms, $this->cacheResolver->rememberUserPerms($user, $app));
        }

        $claims = [
            'sub' => (string) $user->getKey(),
            'apps' => array_values(array_unique($apps)),
            'roles' => array_values(array_unique($roles)),
            'perms' => array_values(array_unique($perms)),
        ];

        if ($application) {
            $claims['application_id'] = $application->getKey();
            $claims['app_key'] = $application->app_key;
        }

        return $claims;
    }

    protected function resolveApplicationsFor(User $user): Collection
    {
        $teamKey = Config::get('permission.column_names.team_foreign_key');
        $modelKey = Config::get('permission.column_names.model_morph_key');
        $roleTable = Config::get('permission.table_names.model_has_roles');
        $permissionTable = Config::get('permission.table_names.model_has_permissions');

        $modelType = $user->getMorphClass();

        $roleAssignments = DB::table($roleTable)
            ->where($modelKey, $user->getKey())
            ->where('model_type', $modelType)
            ->pluck($teamKey);

        $directPermissions = DB::table($permissionTable)
            ->where($modelKey, $user->getKey())
            ->where('model_type', $modelType)
            ->pluck($teamKey);

        $applicationIds = $roleAssignments
            ->merge($directPermissions)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($applicationIds === []) {
            return collect();
        }

        return $this->appRegistry
            ->enabledList()
            ->whereIn('id', $applicationIds)
            ->values();
    }
}
