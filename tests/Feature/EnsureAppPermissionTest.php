<?php

use App\Models\User;
use App\Services\Contracts\RbacServiceContract;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Config::set('cache.default', 'array');
    Config::set('permission.cache.store', 'array');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('allows access when user has application permission', function () {
    $application = makeIamApplication('siimut');
    $user = User::factory()->create();
    $role = makeIamRole($application, 'admin');
    $permission = makeIamPermission($application, 'indicator', 'view')->name;

    /** @var RbacServiceContract $rbac */
    $rbac = app(RbacServiceContract::class);
    $rbac->assignRole($user, $role->name, $application);
    $rbac->syncPermissions($role, [$permission], $application);

    Route::middleware('app.permission:siimut,siimut.indicator.view')
        ->get('/test-permission-allowed', fn () => response()->json(['ok' => true]));

    $this->actingAs($user)
        ->getJson('/test-permission-allowed')
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('forbids access when user lacks permission', function () {
    makeIamApplication('siimut');
    $user = User::factory()->create();

    Route::middleware('app.permission:siimut,siimut.indicator.view')
        ->get('/test-permission-denied', fn () => response()->json(['ok' => true]));

    $this->actingAs($user)
        ->getJson('/test-permission-denied')
        ->assertForbidden();
});
