<?php

use App\Models\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Config::set('cache.default', 'array');
    Config::set('permission.cache.store', 'array');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('seeds applications and scoped roles correctly', function () {
    Artisan::call('db:seed', ['--class' => Database\Seeders\ApplicationsSeeder::class]);
    Artisan::call('db:seed', ['--class' => Database\Seeders\RolesPermissionsSeeder::class]);

    $applications = Application::whereIn('app_key', ['siimut', 'tamasuma'])->pluck('app_key');

    expect($applications)->toContain('siimut')->toContain('tamasuma');

    $teamColumn = Config::get('permission.column_names.team_foreign_key');
    $role = Role::where('name', 'siimut.admin')->first();

    expect($role)->not->toBeNull();
    expect($role?->{$teamColumn})->not->toBeNull();
    expect($role?->{$teamColumn})->toEqual(Application::where('app_key', 'siimut')->first()->getKey());
});
