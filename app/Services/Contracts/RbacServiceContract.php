<?php

namespace App\Services\Contracts;

use App\Models\Application;
use App\Models\User;
use Spatie\Permission\Models\Role;

interface RbacServiceContract
{
    public function assignRole(User $user, string $roleName, Application $application): void;

    public function revokeRole(User $user, string $roleName, Application $application): void;

    public function syncPermissions(Role $role, array $permissionNames, Application $application): void;

    /**
     * @return list<string>
     */
    public function userRoles(User $user, Application $application): array;

    /**
     * @return list<string>
     */
    public function userPermissions(User $user, Application $application): array;

    public function can(User $user, Application $application, string $permission): bool;
}
