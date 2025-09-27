<?php

namespace Database\Seeders;

use App\Models\Application;
use Illuminate\Database\Seeder;

class ApplicationsSeeder extends Seeder
{
    public function run(): void
    {
        $applications = [
            [
                'app_key' => 'siimut',
                'name' => 'Siimut',
                'description' => 'Contoh aplikasi Siimut',
            ],
            [
                'app_key' => 'tamasuma',
                'name' => 'Tamasuma',
                'description' => 'Contoh aplikasi Tamasuma',
            ],
        ];

        foreach ($applications as $data) {
            Application::updateOrCreate([
                'app_key' => $data['app_key'],
            ], $data);
        }
    }
}
