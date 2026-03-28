<?php

return [
    'model' => [
        'unit_kerja' => \Juniyasyos\ManageUnitKerja\Models\UnitKerja::class,
        'user' => \App\Models\User::class,
    ],

    'filament' => [
        'active' => true,
        'resources' => [
            \Juniyasyos\ManageUnitKerja\Filament\Resources\UnitKerjaResource::class,
        ],
    ],

    'app_env' => env('MANAGE_UNIT_KERJA_APP_ENV', env('APP_ENV', 'production')),

    'center_application' => env('MANAGE_UNIT_KERJA_CENTER_APPLICATION', false),

    'app_center_url' => env('MANAGE_UNIT_KERJA_APP_CENTER_URL', 'http://127.0.0.1:8000'),

    'sync' => [
        'active' => env('MANAGE_UNIT_KERJA_SYNC_ACTIVE', true),
    ],

    'navigation_sort' => 0,
];
