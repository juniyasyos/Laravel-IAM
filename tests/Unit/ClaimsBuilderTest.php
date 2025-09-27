<?php

use App\Models\User;
use App\Services\Contracts\ClaimsBuilderContract;
use App\Services\Contracts\RbacServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
    Config::set('permission.cache.store', 'array');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('builds aggregated claims for user across applications', function () {
    $siimut = makeIamApplication('siimut');
    $tamasuma = makeIamApplication('tamasuma');
    $user = User::factory()->create();

    $siimutRole = makeIamRole($siimut, 'admin');
    $tamasumaRole = makeIamRole($tamasuma, 'editor');

    $siimutPerms = [
        makeIamPermission($siimut, 'indicator', 'view')->name,
        makeIamPermission($siimut, 'indicator', 'update')->name,
    ];

    $tamasumaPerm = makeIamPermission($tamasuma, 'course', 'view')->name;

    /** @var RbacServiceContract $rbac */
    $rbac = app(RbacServiceContract::class);

    $rbac->assignRole($user, $siimutRole->name, $siimut);
    $rbac->assignRole($user, $tamasumaRole->name, $tamasuma);
    $rbac->syncPermissions($siimutRole, $siimutPerms, $siimut);
    $rbac->syncPermissions($tamasumaRole, [$tamasumaPerm], $tamasuma);

    /** @var ClaimsBuilderContract $builder */
    $builder = app(ClaimsBuilderContract::class);

    $teamColumn = Config::get('permission.column_names.team_foreign_key');
    $modelRoleRows = DB::table(Config::get('permission.table_names.model_has_roles'))
        ->where('model_id', $user->getKey())
        ->where('model_type', $user->getMorphClass())
        ->pluck($teamColumn);

    expect($modelRoleRows)->toContain($siimut->getKey());
    expect($modelRoleRows)->toContain($tamasuma->getKey());

    expect(Role::where('name', $tamasumaRole->name)->first()?->getAttribute($teamColumn))->toBe($tamasuma->getKey());

    expect($rbac->userRoles($user, $siimut))->toContain($siimutRole->name);
    expect($rbac->userRoles($user, $tamasuma))->toContain($tamasumaRole->name);

    $claims = $builder->build($user);

    expect($claims['apps'])->toMatchArray(['siimut', 'tamasuma']);
    expect($claims['roles'])->toContain($siimutRole->name);
    expect($claims['roles'])->toContain($tamasumaRole->name);
    expect($claims['perms'])->toContain($siimutPerms[0]);
    expect($claims['perms'])->toContain($tamasumaPerm);
});

it('builds claims filtered by application', function () {
    $siimut = makeIamApplication('siimut');
    $user = User::factory()->create();

    $role = makeIamRole($siimut, 'viewer');
    $permission = makeIamPermission($siimut, 'indicator', 'view')->name;

    /** @var RbacServiceContract $rbac */
    $rbac = app(RbacServiceContract::class);
    $rbac->assignRole($user, $role->name, $siimut);
    $rbac->syncPermissions($role, [$permission], $siimut);

    /** @var ClaimsBuilderContract $builder */
    $builder = app(ClaimsBuilderContract::class);

    $claims = $builder->build($user, $siimut);

    expect($claims['app_key'])->toBe('siimut');
    expect($claims['application_id'])->toBe($siimut->getKey());
    expect($claims['roles'])->toMatchArray([$role->name]);
    expect($claims['perms'])->toMatchArray([$permission]);
});
