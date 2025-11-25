<?php

namespace App\Domain\Iam\Services;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\Role;
use App\Domain\Iam\Models\UserApplicationRole;
use App\Models\User;
use Illuminate\Support\Collection;

class UserRoleAssignmentService
{
    /**
     * Assign a role to a user.
     *
     * @throws \Exception
     */
    public function assignRoleToUser(User $user, UserApplicationRole $role, ?User $assignedBy = null): void
    {
        // Check if user already has this role
        $existing = UserApplicationRole::where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->first();

        if ($existing) {
            throw new \Exception("User already has role '{$role->name}' for application '{$role->application->app_key}'.");
        }

        UserApplicationRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'assigned_by' => $assignedBy?->id,
        ]);
    }

    /**
     * Revoke a role from a user.
     */
    public function revokeRoleFromUser(User $user, UserApplicationRole $role): void
    {
        UserApplicationRole::where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->delete();
    }

    /**
     * Sync roles for a user in a specific application.
     * This will replace all existing roles for the app with the provided role slugs.
     *
     * @param  array<string>  $roleSlugs
     *
     * @throws \Exception
     */
    public function syncRolesForUserAndApp(User $user, Application $app, array $roleSlugs, ?User $assignedBy = null): void
    {
        // Get all role IDs for this application
        $roles = UserApplicationRole::where('application_id', $app->id)
            ->whereIn('slug', $roleSlugs)
            ->get();

        if ($roles->count() !== count($roleSlugs)) {
            $found = $roles->pluck('slug')->toArray();
            $missing = array_diff($roleSlugs, $found);
            throw new \Exception('Invalid role slugs: '.implode(', ', $missing));
        }

        // Get current role IDs for this app
        $currentRoleIds = UserApplicationRole::where('user_id', $user->id)
            ->whereHas('role', function ($query) use ($app) {
                $query->where('application_id', $app->id);
            })
            ->pluck('role_id')
            ->toArray();

        $newRoleIds = $roles->pluck('id')->toArray();

        // Remove roles that are not in the new list
        $toRemove = array_diff($currentRoleIds, $newRoleIds);
        if (! empty($toRemove)) {
            UserApplicationRole::where('user_id', $user->id)
                ->whereIn('role_id', $toRemove)
                ->delete();
        }

        // Add roles that are not in the current list
        $toAdd = array_diff($newRoleIds, $currentRoleIds);
        foreach ($toAdd as $roleId) {
            UserApplicationRole::create([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'assigned_by' => $assignedBy?->id,
            ]);
        }
    }

    /**
     * Get roles grouped by app_key for a user.
     * Returns: ['app_key' => ['slug1', 'slug2'], ...].
     *
     * @return array<string, array<string>>
     */
    public function getRolesByAppForUser(User $user): array
    {
        return $user->rolesByApp();
    }

    /**
     * Get list of app_keys that the user has access to.
     *
     * @return array<string>
     */
    public function getAppsForUser(User $user): array
    {
        return $user->accessibleApps();
    }

    /**
     * Get all roles assigned to a user for a specific application.
     */
    public function getRolesForUserInApp(User $user, Application $app): Collection
    {
        return UserApplicationRole::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->where('application_id', $app->id)
            ->get();
    }

    /**
     * Check if user has a specific role in an application.
     */
    public function userHasRole(User $user, string $appKey, string $roleSlug): bool
    {
        $rolesByApp = $this->getRolesByAppForUser($user);

        return isset($rolesByApp[$appKey]) && in_array($roleSlug, $rolesByApp[$appKey]);
    }

    /**
     * Get all users with a specific role.
     */
    public function getUsersWithRole(UserApplicationRole $role): Collection
    {
        return $role->users;
    }

    /**
     * Revoke all roles from a user for a specific application.
     */
    public function revokeAllRolesForUserInApp(User $user, Application $app): void
    {
        UserApplicationRole::where('user_id', $user->id)
            ->whereHas('role', function ($query) use ($app) {
                $query->where('application_id', $app->id);
            })
            ->delete();
    }
}
