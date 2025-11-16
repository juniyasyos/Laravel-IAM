<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('seeds_default_applications', function () {
    Artisan::call('db:seed', ['--class' => Database\Seeders\ApplicationsSeeder::class]);

    $apps = \use App\Domain\Iam\Models\Application;::pluck('enabled', 'app_key');

    expect($apps->keys())->toContain('siimut')->toContain('tamasuma');
    expect($apps['siimut'])->toBeTrue();
    expect($apps['tamasuma'])->toBeTrue();
});

it('applications_seeder_is_idempotent', function () {
    Artisan::call('db:seed', ['--class' => Database\Seeders\ApplicationsSeeder::class]);
    Artisan::call('db:seed', ['--class' => Database\Seeders\ApplicationsSeeder::class]);

    // Seeder now creates 5 applications (client-example, siimut, tamasuma, incident-report.app, pharmacy.app)
    expect(\use App\Domain\Iam\Models\Application;::count())->toBe(5);
});
