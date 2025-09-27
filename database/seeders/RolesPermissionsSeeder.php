<?php

namespace Database\Seeders;

use App\Models\Application;
use Illuminate\Database\Seeder;
use App\Services\Contracts\RbacServiceContract;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $definitions = [
            'siimut' => [
                'resources' => ['indicator', 'report'],
                'roles' => [
                    'admin' => ['indicator.view', 'indicator.create', 'indicator.update', 'indicator.delete', 'report.view', 'report.create'],
                    'editor' => ['indicator.view', 'indicator.create', 'indicator.update', 'report.view'],
                    'viewer' => ['indicator.view', 'report.view'],
                ],
            ],
            'tamasuma' => [
                'resources' => ['course', 'enrollment'],
                'roles' => [
                    'admin' => ['course.view', 'course.create', 'course.update', 'course.delete', 'enrollment.view', 'enrollment.manage'],
                    'editor' => ['course.view', 'course.create', 'course.update', 'enrollment.view'],
                    'viewer' => ['course.view', 'enrollment.view'],
                ],
            ],
        ];

        $teamColumn = Config::get('permission.column_names.team_foreign_key', 'application_id');

        $rbac = app(RbacServiceContract::class);

        foreach ($definitions as $appKey => $config) {
            $application = Application::where('app_key', $appKey)->first();

            if (! $application) {
                continue;
            }

            $permissions = $this->seedPermissions($appKey, $config['resources']);

            foreach ($config['roles'] as $roleKey => $permissionSuffixes) {
                $roleName = sprintf('%s.%s', $appKey, $roleKey);

                $role = Role::query()->firstOrCreate(
                    [
                        'name' => $roleName,
                        'guard_name' => 'web',
                        $teamColumn => $application->getKey(),
                    ]
                );

                $permissionNames = array_map(
                    fn ($suffix) => sprintf('%s.%s', $appKey, $suffix),
                    $permissionSuffixes
                );

                $rbac->syncPermissions($role, $permissionNames, $application);
            }
        }
    }

    protected function seedPermissions(string $appKey, array $resources): array
    {
        $actions = [
            'view',
            'create',
            'update',
            'delete',
        ];

        $extraActions = [
            'report' => ['export'],
            'enrollment' => ['manage'],
        ];

        $all = [];

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                $all[] = sprintf('%s.%s.%s', $appKey, $resource, $action);
            }

            foreach ($extraActions[$resource] ?? [] as $action) {
                $all[] = sprintf('%s.%s.%s', $appKey, $resource, $action);
            }
        }

        foreach ($all as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        return $all;
    }
}
