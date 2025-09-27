<?php

use App\Models\Application;
use App\Models\User;
use App\Services\Contracts\RbacServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
    Config::set('permission.cache.store', 'array');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('assigns and revokes roles within the application context', function () {
    $application = makeIamApplication();
    $user = User::factory()->create();
    $role = makeIamRole($application, 'admin');

    /** @var RbacServiceContract $service */
    $service = app(RbacServiceContract::class);

    $service->assignRole($user, $role->name, $application);

    expect($service->userRoles($user, $application))->toContain($role->name);

    $service->revokeRole($user, $role->name, $application);

    expect($service->userRoles($user, $application))->not->toContain($role->name);
});

it('syncs permissions for a role and resolves via user context', function () {
    $application = makeIamApplication();
    $user = User::factory()->create();
    $role = makeIamRole($application, 'editor');

    $permissions = [
        makeIamPermission($application, 'indicator', 'view')->name,
        makeIamPermission($application, 'indicator', 'update')->name,
    ];

    /** @var RbacServiceContract $service */
    $service = app(RbacServiceContract::class);

    $service->assignRole($user, $role->name, $application);
    $service->syncPermissions($role, $permissions, $application);

    expect($service->userPermissions($user, $application))->toMatchArray($permissions);
    expect($service->can($user, $application, $permissions[0]))->toBeTrue();
});
