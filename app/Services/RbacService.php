<?php

namespace App\Services;

use App\Events\PermissionSynced;
use App\Events\RoleAssigned as AppRoleAssigned;
use App\Events\RoleRevoked as AppRoleRevoked;
use App\Models\Application;
use App\Models\User;
use App\Services\Contracts\RbacServiceContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacService implements RbacServiceContract
{
    public function __construct(
        private readonly PermissionRegistrar $permissionRegistrar,
        private readonly Dispatcher $events
    ) {
    }

    public function assignRole(User $user, string $roleName, Application $application): void
    {
        $this->assertRoleBelongsToApplication($roleName, $application);

        $this->runInContext($user, $application, function () use ($user, $roleName, $application): void {
            $user->assignRole($roleName);
            $this->events->dispatch(new AppRoleAssigned($user, $roleName, $application));
        });
    }

    public function revokeRole(User $user, string $roleName, Application $application): void
    {
        $this->assertRoleBelongsToApplication($roleName, $application);

        $this->runInContext($user, $application, function () use ($user, $roleName, $application): void {
            $user->removeRole($roleName);
            $this->events->dispatch(new AppRoleRevoked($user, $roleName, $application));
        });
    }

    public function syncPermissions(Role $role, array $permissionNames, Application $application): void
    {
        $this->assertRoleScopedToApplication($role, $application);

        $validPermissions = collect($permissionNames)
            ->filter()
            ->map(function (string $permission) use ($application) {
                $this->assertPermissionBelongsToApplication($permission, $application);

                return $permission;
            })
            ->values();

        $permissionModels = Permission::query()
            ->whereIn('name', $validPermissions)
            ->get();

        $syncPayload = $permissionModels
            ->mapWithKeys(function (Permission $permission) use ($application) {
                $teamColumn = Config::get('permission.column_names.team_foreign_key');

                return [$permission->getKey() => [$teamColumn => $application->getKey()]];
            })
            ->toArray();

        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();
        $this->permissionRegistrar->setPermissionsTeamId($application->getKey());

        try {
            $role->permissions()->sync($syncPayload);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->events->dispatch(new PermissionSynced($role, $validPermissions->all(), $application));
        } finally {
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }

    public function userRoles(User $user, Application $application): array
    {
        return $this->runInContext($user, $application, function () use ($user, $application) {
            $teamColumn = Config::get('permission.column_names.team_foreign_key');
            $modelKey = Config::get('permission.column_names.model_morph_key');
            $rolePivot = Config::get('permission.column_names.role_pivot_key', 'role_id') ?: 'role_id';
            $table = Config::get('permission.table_names.model_has_roles');

            $roleIds = DB::table($table)
                ->where($teamColumn, $application->getKey())
                ->where($modelKey, $user->getKey())
                ->where('model_type', $user->getMorphClass())
                ->pluck($rolePivot);

            if ($roleIds->isEmpty()) {
                return [];
            }

            return Role::whereIn('id', $roleIds)->pluck('name')->values()->all();
        });
    }

    public function userPermissions(User $user, Application $application): array
    {
        return $this->runInContext($user, $application, function () use ($user, $application) {
            $teamColumn = Config::get('permission.column_names.team_foreign_key');
            $modelKey = Config::get('permission.column_names.model_morph_key');
            $pivotPermission = Config::get('permission.column_names.permission_pivot_key', 'permission_id') ?: 'permission_id';
            $pivotRole = Config::get('permission.column_names.role_pivot_key', 'role_id') ?: 'role_id';

            $permissionTable = Config::get('permission.table_names.permissions');
            $directTable = Config::get('permission.table_names.model_has_permissions');
            $modelRoleTable = Config::get('permission.table_names.model_has_roles');
            $rolePermissionTable = Config::get('permission.table_names.role_has_permissions');

            $directPermissions = DB::table($directTable)
                ->join($permissionTable, "$permissionTable.id", '=', "$directTable.$pivotPermission")
                ->where("$directTable.$modelKey", $user->getKey())
                ->where("$directTable.model_type", $user->getMorphClass())
                ->where("$directTable.$teamColumn", $application->getKey())
                ->pluck("$permissionTable.name");

            $viaRoles = DB::table($modelRoleTable)
                ->join($rolePermissionTable, function ($join) use ($modelRoleTable, $rolePermissionTable, $pivotRole, $teamColumn, $application) {
                    $join->on("$modelRoleTable.$pivotRole", '=', "$rolePermissionTable.$pivotRole")
                        ->where("$rolePermissionTable.$teamColumn", $application->getKey());
                })
                ->join($permissionTable, "$permissionTable.id", '=', "$rolePermissionTable.$pivotPermission")
                ->where("$modelRoleTable.$modelKey", $user->getKey())
                ->where("$modelRoleTable.model_type", $user->getMorphClass())
                ->where("$modelRoleTable.$teamColumn", $application->getKey())
                ->pluck("$permissionTable.name");

            return $directPermissions
                ->merge($viaRoles)
                ->unique()
                ->values()
                ->all();
        });
    }

    public function can(User $user, Application $application, string $permission): bool
    {
        $this->assertPermissionBelongsToApplication($permission, $application);

        return $this->runInContext($user, $application, static fn () => $user->can($permission));
    }

    protected function assertRoleBelongsToApplication(string $roleName, Application $application): void
    {
        if (! $this->startsWithAppKey($roleName, $application)) {
            throw new InvalidArgumentException('Role name must be prefixed with the application key.');
        }
    }

    protected function assertPermissionBelongsToApplication(string $permission, Application $application): void
    {
        if (! $this->startsWithAppKey($permission, $application)) {
            throw new InvalidArgumentException('Permission name must be prefixed with the application key.');
        }
    }

    protected function assertRoleScopedToApplication(Role $role, Application $application): void
    {
        $teamColumn = Config::get('permission.column_names.team_foreign_key');

        if ((int) $role->getAttribute($teamColumn) !== (int) $application->getKey()) {
            throw new InvalidArgumentException('Role does not belong to the provided application context.');
        }
    }

    protected function startsWithAppKey(string $name, Application $application): bool
    {
        return Str::of($name)->startsWith($application->app_key . '.');
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    protected function runInContext(User $user, Application $application, callable $callback)
    {
        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();
        $previousApplication = $user->currentApplication();

        $user->setCurrentApplication($application);
        $this->permissionRegistrar->setPermissionsTeamId($application->getKey());

        try {
            return $callback();
        } finally {
            $user->setCurrentApplication($previousApplication);
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }
}
