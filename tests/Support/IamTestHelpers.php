<?php

use App\Models\Application;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function makeIamApplication(string $key = 'siimut'): Application
{
    return Application::create([
        'app_key' => $key,
        'name' => ucfirst($key),
        'enabled' => true,
    ]);
}

function makeIamRole(Application $application, string $role): Role
{
    $teamColumn = Config::get('permission.column_names.team_foreign_key');

    return Role::query()->firstOrCreate([
        'name' => sprintf('%s.%s', $application->app_key, $role),
        'guard_name' => 'web',
        $teamColumn => $application->getKey(),
    ]);
}

function makeIamPermission(Application $application, string $resource, string $action): Permission
{
    return Permission::findOrCreate(sprintf('%s.%s.%s', $application->app_key, $resource, $action));
}
