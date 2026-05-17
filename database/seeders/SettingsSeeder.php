<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Services\SettingService;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $count = app(SettingService::class)->syncFromDefinitions();

        $this->command?->info('Settings seeded: ' . $count);
    }
}
