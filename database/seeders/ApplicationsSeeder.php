<?php

namespace Database\Seeders;

use App\Domain\Iam\Models\Application;
use App\Models\User;
use Illuminate\Database\Seeder;

class ApplicationsSeeder extends Seeder
{
    public function run(): void
    {
        // Get admin user untuk created_by
        $admin = User::where('nip', '0000.00000')->first();

        $applications = [
            [
                'app_key' => 'siimut',
                'name' => 'SIIMUT - Sistem Informasi Manajemen Indikator Mutu Terpadu',
                'description' => 'Aplikasi manajemen indikator kinerja mutu rumah sakit dan unit kerja',
                'enabled' => true,
                'redirect_uris' => [
                    'http://127.0.0.1:8000',
                ],
                'callback_url' => 'http://127.0.0.1:8000/sso/callback',
                'secret' => 'siimut_secret_key_123',
                'logo_url' => null,
                'token_expiry' => 3600,
                'created_by' => $admin?->id,
            ],
            // [
            //     'app_key' => 'incident-report.app',
            //     'name' => 'Incident Reporting System',
            //     'description' => 'Sistem pelaporan insiden keselamatan pasien',
            //     'enabled' => true,
            //     'redirect_uris' => [
            //         'http://localhost:3002/auth/callback',
            //         'https://incident.rs.id/auth/callback',
            //     ],
            //     'callback_url' => 'https://incident.rs.id/auth/callback',
            //     'secret' => 'incident_secret_key_789',
            //     'logo_url' => null,
            //     'token_expiry' => 7200,
            //     'created_by' => $admin?->id,
            // ],
        ];

        foreach ($applications as $data) {
            Application::updateOrCreate(
                ['app_key' => $data['app_key']],
                $data
            );
        }

        $this->command->info('Applications seeded successfully!');
    }
}
