<?php

use App\Actions\ImportUnitKerjasFromJsonAction;
use App\Jobs\ImportUnitKerjasFromJsonJob;
use App\Models\UnitKerja;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function unitKerjaImportFixture(): array
{
    return json_decode(
        file_get_contents(base_path('database/unit_kerja.json')),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
}

it('imports all 33 unit kerja records from json fixture', function () {
    $data = unitKerjaImportFixture();

    $result = app(ImportUnitKerjasFromJsonAction::class)->execute($data, skipErrors: false);

    expect($result['total'])->toBe(33);
    expect($result['created'])->toBe(33);
    expect($result['updated'])->toBe(0);
    expect($result['failed'])->toBe(0);
    expect($result['errors'])->toBeEmpty();

    expect(UnitKerja::count())->toBe(33);
});

it('verifies all imported units have correct attributes', function () {
    $data = unitKerjaImportFixture();

    app(ImportUnitKerjasFromJsonAction::class)->execute($data, skipErrors: false);

    // Check sample units
    $igd = UnitKerja::where('unit_name', 'IGD test edit')->first();
    expect($igd)->not->toBeNull();
    expect($igd?->slug)->toBe('igd-test-edit');
    expect($igd?->description)->toContain('Unit Gawat Darurat');

    $icu = UnitKerja::where('unit_name', 'ICU')->first();
    expect($icu)->not->toBeNull();
    expect($icu?->slug)->toBe('icu');
    expect($icu?->description)->toContain('Intensive Care Unit');

    $lab = UnitKerja::where('unit_name', 'Laboratorium')->first();
    expect($lab)->not->toBeNull();
    expect($lab?->slug)->toBe('laboratorium');
});

it('handles upsert when importing duplicate unit names', function () {
    $data = unitKerjaImportFixture();

    // First import
    $result1 = app(ImportUnitKerjasFromJsonAction::class)->execute($data, skipErrors: false);
    expect($result1['created'])->toBe(33);
    expect($result1['updated'])->toBe(0);

    // Second import with updated description
    $data[0]['description'] = 'Updated description for IGD';
    $result2 = app(ImportUnitKerjasFromJsonAction::class)->execute([$data[0]], skipErrors: false);

    expect($result2['created'])->toBe(0);
    expect($result2['updated'])->toBe(1);

    $igd = UnitKerja::where('unit_name', 'IGD test edit')->first();
    expect($igd?->description)->toBe('Updated description for IGD');
});

it('restores soft deleted records during import', function () {
    $data = unitKerjaImportFixture();

    // Insert a soft-deleted record
    DB::table('unit_kerja')->insert([
        'id' => 1,
        'unit_name' => 'IGD test edit',
        'slug' => 'igd-test-edit',
        'description' => 'Old description',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
        'deleted_at' => now()->subMinute(),
    ]);

    $result = app(ImportUnitKerjasFromJsonAction::class)->execute($data, skipErrors: false);

    expect($result['updated'])->toBe(1); // The soft-deleted record was restored and updated
    expect($result['created'])->toBe(32); // 33 - 1 (the restored one)

    $igd = UnitKerja::where('unit_name', 'IGD test edit')->first();
    expect($igd?->deleted_at)->toBeNull();
    expect($igd?->description)->toBe(
        'Unit Gawat Darurat (IGD) menangani kasus-kasus medis darurat yang membutuhkan penanganan cepat dan tepat selama 24 jam.'
    );
});

it('imports the provided json payload and restores matching soft deleted records', function () {
    $data = unitKerjaImportFixture();

    DB::table('unit_kerja')->insert([
        'id' => 1,
        'unit_name' => 'IGD Lama',
        'slug' => 'igd',
        'description' => 'Data lama yang akan di-restore saat import berjalan.',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
        'deleted_at' => now()->subMinute(),
    ]);

    $result = app(ImportUnitKerjasFromJsonAction::class)->execute($data);

    expect($result['total'])->toBe(33);
    expect($result['created'])->toBe(32);
    expect($result['updated'])->toBe(1);
    expect($result['failed'])->toBe(0);
    expect($result['errors'])->toBe([]);

    expect(UnitKerja::count())->toBe(33);

    $igd = UnitKerja::where('unit_name', 'IGD test edit')->first();

    expect($igd)->not->toBeNull();
    expect($igd?->slug)->toBe('igd-test-edit');
    expect($igd?->deleted_at)->toBeNull();
    expect($igd?->description)->toBe(
        'Unit Gawat Darurat (IGD) menangani kasus-kasus medis darurat yang membutuhkan penanganan cepat dan tepat selama 24 jam.'
    );
});

it('processes the json file in the job and deletes the source file after completion', function () {
    config(['iam.imports.delete_source_after_import' => true]);

    $data = unitKerjaImportFixture();
    $filePath = 'imports/unit-kerja.json';

    Storage::fake('s3');
    Storage::disk('s3')->put($filePath, json_encode($data, JSON_THROW_ON_ERROR));

    Cache::shouldReceive('put')
        ->once()
        ->withArgs(function ($key, $value, $ttl) {
            return is_string($key)
                && $value['import_id'] === 'import-123'
                && $value['status'] === 'running'
                && $value['message'] === 'Membaca file JSON...'
                && $ttl instanceof DateTimeInterface;
        })
        ->andReturnTrue();

    Cache::shouldReceive('put')
        ->once()
        ->withArgs(function ($key, $value, $ttl) use ($data) {
            return is_string($key)
                && $value['import_id'] === 'import-123'
                && $value['status'] === 'completed'
                && $value['message'] === 'Import unit kerja selesai.'
                && $value['total'] === count($data)
                && $value['processed'] === count($data)
                && $value['created'] === count($data)
                && $value['updated'] === 0
                && $value['failed'] === 0
                && $value['errors'] === []
                && $ttl instanceof DateTimeInterface;
        })
        ->andReturnTrue();

    $action = \Mockery::mock(ImportUnitKerjasFromJsonAction::class);
    $action->shouldReceive('execute')
        ->once()
        ->withArgs(function (array $payload, bool $skipErrors, callable $onProgress) use ($data) {
            return $payload === $data && $skipErrors === true;
        })
        ->andReturn([
            'total' => count($data),
            'created' => count($data),
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ]);

    $job = new ImportUnitKerjasFromJsonJob('import-123', $filePath, 99);
    $job->handle($action);

    expect(Storage::disk('s3')->exists($filePath))->toBeFalse();
});

it('counts correct total units after batch import', function () {
    $data = unitKerjaImportFixture();

    app(ImportUnitKerjasFromJsonAction::class)->execute($data, skipErrors: false);

    $allUnits = UnitKerja::withoutTrashed()->get();
    expect($allUnits->count())->toBe(33);

    // Verify some specific units exist
    expect($allUnits->where('slug', 'laboratorium')->count())->toBe(1);
    expect($allUnits->where('slug', 'ok')->count())->toBe(1);
    expect($allUnits->where('slug', 'unit-cssd')->count())->toBe(1);
});

it('handles crud guard correctly during import', function () {
    $data = unitKerjaImportFixture();

    // Ensure crud is allowed
    expect(UnitKerja::isCrudAllowed())->toBeTrue();

    $result = app(ImportUnitKerjasFromJsonAction::class)->execute($data, skipErrors: false);

    expect($result['failed'])->toBe(0);
    expect($result['errors'])->toBeEmpty();
});
