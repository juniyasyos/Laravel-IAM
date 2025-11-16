<?php

use  App\Domain\Iam\Models\Application;;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('finds_by_key_and_respects_enabled_scope', function () {
    $enabled = Application::factory()->create(['app_key' => 'siimut', 'enabled' => true]);
    Application::factory()->create(['app_key' => 'tamasuma', 'enabled' => false]);

    expect(Application::enabled()->pluck('id'))->toContain($enabled->getKey());
    expect(Application::findByKey('siimut'))->toBeInstanceOf(Application::class);
});

it('casts_redirect_uris_to_array', function () {
    $app = Application::factory()->create([
        'redirect_uris' => ['https://example.com/callback'],
    ]);

    expect($app->redirect_uris)->toBeArray();
});

it('enforces_unique_app_key', function () {
    Application::factory()->create(['app_key' => 'siimut']);

    expect(fn () => Application::factory()->create(['app_key' => 'siimut']))
        ->toThrow(QueryException::class);
});
