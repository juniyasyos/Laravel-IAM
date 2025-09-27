<?php

use App\Models\User;
use App\Services\Contracts\CacheResolverContract;
use App\Services\Contracts\RbacServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
    Config::set('permission.cache.store', 'array');

    Cache::store('array')->clear();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('remembers and invalidates user permissions per application', function () {
    $application = makeIamApplication();
    $user = User::factory()->create();
    $role = makeIamRole($application, 'admin');
    $permission = makeIamPermission($application, 'indicator', 'view')->name;

    /** @var RbacServiceContract $rbac */
    $rbac = app(RbacServiceContract::class);
    /** @var CacheResolverContract $cache */
    $cache = app(CacheResolverContract::class);

    $rbac->assignRole($user, $role->name, $application);
    $rbac->syncPermissions($role, [$permission], $application);

    $cached = $cache->rememberUserPerms($user, $application);

    expect($cached)->toContain($permission);

    $cacheKey = sprintf('perms:%d:%d', $user->getKey(), $application->getKey());
    expect(Cache::store('array')->get($cacheKey))->toContain($permission);

    $cache->invalidateUser($user, $application);

    expect(Cache::store('array')->get($cacheKey))->toBeNull();
});

it('invalidates all application caches for a user when no application provided', function () {
    $firstApp = makeIamApplication('siimut');
    $secondApp = makeIamApplication('tamasuma');
    $user = User::factory()->create();
    $roleOne = makeIamRole($firstApp, 'admin');
    $roleTwo = makeIamRole($secondApp, 'admin');

    $permOne = makeIamPermission($firstApp, 'indicator', 'view')->name;
    $permTwo = makeIamPermission($secondApp, 'course', 'view')->name;

    /** @var RbacServiceContract $rbac */
    $rbac = app(RbacServiceContract::class);
    /** @var CacheResolverContract $cache */
    $cache = app(CacheResolverContract::class);

    $rbac->assignRole($user, $roleOne->name, $firstApp);
    $rbac->assignRole($user, $roleTwo->name, $secondApp);
    $rbac->syncPermissions($roleOne, [$permOne], $firstApp);
    $rbac->syncPermissions($roleTwo, [$permTwo], $secondApp);

    $cache->rememberUserPerms($user, $firstApp);
    $cache->rememberUserPerms($user, $secondApp);

    $cache->invalidateUser($user);

    expect(Cache::store('array')->get(sprintf('perms:%d:%d', $user->getKey(), $firstApp->getKey())))->toBeNull();
    expect(Cache::store('array')->get(sprintf('perms:%d:%d', $user->getKey(), $secondApp->getKey())))->toBeNull();
});
