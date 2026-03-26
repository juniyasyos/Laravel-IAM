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
     * When non-empty, only these access profile IDs are considered when
     * syncing.  Used by the bulk sync job.
     *
     * @var array<int>
     */
    protected array $allowedProfileIds = [];

    /**
     * Configure which profiles may be used during role/profile sync. If left
     * empty all bundles are permitted.
     */
    public function setAllowedProfileIds(array $ids): void
    {
        $this->allowedProfileIds = $ids;
    }

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

        $data = [
            'user_id' => $user->id,
            'role_id' => $role->id,
            'assigned_by' => $assignedBy?->id,
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn('iam_user_application_roles', 'application_id')) {
            $data['application_id'] = $role->application_id;
        }
        UserApplicationRole::create($data);
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
    /**
     * Sync roles for a user in a specific application by assigning *access
     * profiles* (aka role bundles) instead of attaching the role records
     * directly.  The client gives us a list of role slugs, but the database
     * model only links users -> access_profiles, and profiles themselves
     * contain the application roles.  This helper ensures the user is paired
     * with every profile that contains one of the requested slugs, and removes
     * profiles that no longer match the application.
     *
     * This method replaces the old direct-assignment behaviour and will throw
     * an exception if any slug is invalid.
     *
     * @param  array<string>  $roleSlugs
     *
     * @throws \Exception
     */
    public function syncProfilesForUserAndApp(User $user, Application $app, array $roleSlugs, ?User $assignedBy = null): void
    {
        // always validate role slugs against the application roles table.
        // this avoids touching the old pivot entirely and works whether or not
        // the migration has been executed.
        $roles = \App\Domain\Iam\Models\ApplicationRole::where('application_id', $app->id)
            ->whereIn('slug', $roleSlugs)
            ->get();

        if ($roles->count() !== count($roleSlugs)) {
            $found = $roles->pluck('slug')->toArray();
            $missing = array_diff($roleSlugs, $found);
            throw new \Exception('Invalid role slugs: ' . implode(', ', $missing));
        }

        // find all profiles that reference at least one of the supplied roles
        $existingProfiles = \App\Domain\Iam\Models\AccessProfile::query()
            ->whereHas('roles', function ($q) use ($app, $roleSlugs) {
                $q->where('application_id', $app->id)
                    ->whereIn('slug', $roleSlugs);
            })
            ->with('roles')
            ->get();

        $profileIds = $existingProfiles->pluck('id')->toArray();

        if (! empty($this->allowedProfileIds)) {
            $profileIds = array_intersect($profileIds, $this->allowedProfileIds);
        }

        $coveredSlugs = $existingProfiles
            ->flatMap(fn($p) => $p->roles->pluck('slug'))
            ->unique()
            ->toArray();

        $missingSlugs = array_diff($roleSlugs, $coveredSlugs);
        if (! empty($missingSlugs) && empty($this->allowedProfileIds)) {
            foreach ($missingSlugs as $slug) {
                $role = \App\Domain\Iam\Models\ApplicationRole::where('application_id', $app->id)
                    ->where('slug', $slug)
                    ->first();

                if (! $role) {
                    continue;
                }

                $profile = \App\Domain\Iam\Models\AccessProfile::create([
                    'slug' => 'auto_'.$app->app_key.'_'.$slug,
                    'name' => 'Auto '.$slug,
                    'description' => 'Automatically created bundle for '. $slug,
                    'is_system' => false,
                    'is_active' => true,
                ]);
                $profile->roles()->attach($role->id);
                $profileIds[] = $profile->id;
            }
        }

        // find all profiles that reference at least one of the supplied roles
        $existingProfiles = \App\Domain\Iam\Models\AccessProfile::query()
            ->whereHas('roles', function ($q) use ($app, $roleSlugs) {
                $q->where('application_id', $app->id)
                    ->whereIn('slug', $roleSlugs);
            })
            ->with('roles')
            ->get();

        $profileIds = $existingProfiles->pluck('id')->toArray();

        // if the caller restricted to a subset of profiles, apply that filter
        if (! empty($this->allowedProfileIds)) {
            $profileIds = array_intersect($profileIds, $this->allowedProfileIds);
        }

        // compute which slugs are already covered by the profiles we found
        $coveredSlugs = $existingProfiles
            ->flatMap(fn($p) => $p->roles->pluck('slug'))
            ->unique()
            ->toArray();

        // for any slug that isn't covered yet, create an "auto" bundle – but
        // only when we are not running in restricted mode.  if an admin has
        // specified a subset of profiles, we assume they don't want new ones.
        $missingSlugs = array_diff($roleSlugs, $coveredSlugs);
        if (! empty($missingSlugs) && empty($this->allowedProfileIds)) {
            foreach ($missingSlugs as $slug) {
                $role = \App\Domain\Iam\Models\ApplicationRole::where('application_id', $app->id)
                    ->where('slug', $slug)
                    ->first();

                if (! $role) {
                    // should be impossible since we checked earlier, but guard anyway
                    continue;
                }

                $profile = \App\Domain\Iam\Models\AccessProfile::create([
                    'slug' => 'auto_'.$app->app_key.'_'.$slug,
                    'name' => 'Auto '.$slug,
                    'description' => 'Automatically created bundle for '. $slug,
                    'is_system' => false,
                    'is_active' => true,
                ]);
                $profile->roles()->attach($role->id);
                $profileIds[] = $profile->id;
            }
        }

        // current profiles of user that relate to this app; we will only add
        // new bundles and never remove existing ones, because removals should
        // be explicit. previously the code detached anything that wasn't part
        // of the incoming list, which caused associations to vanish during
        // sync if the client payload didn't include the corresponding role.
        $currentProfileIds = $user->accessProfiles()
            ->whereHas('roles', function ($q) use ($app) {
                $q->where('application_id', $app->id);
            })
            ->pluck('access_profiles.id')
            ->toArray();

        // attach only profiles that are not already present
        $toAdd = array_diff($profileIds, $currentProfileIds);
        if (! empty($toAdd)) {
            $user->accessProfiles()->attach($toAdd, ['assigned_by' => $assignedBy?->id]);
        }
    }

    /**
     * @deprecated use {@see syncProfilesForUserAndApp} instead. kept for
     * backwards compatibility until callers are updated.
     */
    public function syncRolesForUserAndApp(User $user, Application $app, array $roleSlugs, ?User $assignedBy = null): void
    {
        $this->syncProfilesForUserAndApp($user, $app, $roleSlugs, $assignedBy);
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
     * Ensure the user has access profiles matching any direct app roles they already have.
     * This supports upgrade path where roles may be assigned directly first.
     */
    public function syncProfilesFromExistingAppRoles(User $user, Application $app): void
    {
        $roleSlugs = $user->applicationRoles()
            ->where('application_id', $app->id)
            ->pluck('slug')
            ->toArray();

        if (empty($roleSlugs)) {
            return;
        }

        // Avoid infinite loop: call syncProfilesForUserAndApp with current role slugs.
        $this->syncProfilesForUserAndApp($user, $app, $roleSlugs);
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
