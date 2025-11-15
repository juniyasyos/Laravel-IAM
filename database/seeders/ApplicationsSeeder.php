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
        $admin = User::where('email', 'admin@gmail.com')->first();

        $applications = [
            [
                'app_key' => 'client-example',
                'name' => 'Aplikasi Contoh Client',
                'description' => 'Aplikasi contoh untuk demonstrasi integrasi OAuth2',
                'enabled' => true,
                'redirect_uris' => [
                    'http://127.0.0.1:8080/auth/callback',
                ],
                'callback_url' => 'http://127.0.0.1:8080/auth/callback',
                'secret' => 'client_example_secret_789',
                'logo_url' => null,
                'token_expiry' => 9600,
                'created_by' => $admin?->id,
            ],
            [
                'app_key' => 'siimut',
                'name' => 'SIIMUT - Sistem Informasi Manajemen RS',
                'description' => 'Aplikasi utama untuk manajemen rumah sakit termasuk pasien, perawatan, dan administrasi',
                'enabled' => true,
                'redirect_uris' => [
                    'http://localhost:3000/auth/callback',
                    'https://siimut.rs.id/auth/callback',
                ],
                'callback_url' => 'https://siimut.rs.id/auth/callback',
                'secret' => 'siimut_secret_key_123',
                'logo_url' => null,
                'token_expiry' => 3600,
                'created_by' => $admin?->id,
            ],
            [
                'app_key' => 'tamasuma',
                'name' => 'Tamasuma - Sistem Manajemen Unit',
                'description' => 'Aplikasi manajemen unit kerja dan resource allocation',
                'enabled' => true,
                'redirect_uris' => [
                    'http://localhost:3001/auth/callback',
                    'https://tamasuma.rs.id/auth/callback',
                ],
                'callback_url' => 'https://tamasuma.rs.id/auth/callback',
                'secret' => 'tamasuma_secret_key_456',
                'logo_url' => null,
                'token_expiry' => 3600,
                'created_by' => $admin?->id,
            ],
            [
                'app_key' => 'incident-report.app',
                'name' => 'Incident Reporting System',
                'description' => 'Sistem pelaporan insiden keselamatan pasien',
                'enabled' => true,
                'redirect_uris' => [
                    'http://localhost:3002/auth/callback',
                    'https://incident.rs.id/auth/callback',
                ],
                'callback_url' => 'https://incident.rs.id/auth/callback',
                'secret' => 'incident_secret_key_789',
                'logo_url' => null,
                'token_expiry' => 7200,
                'created_by' => $admin?->id,
            ],
            [
                'app_key' => 'pharmacy.app',
                'name' => 'Pharmacy Management System',
                'description' => 'Sistem manajemen farmasi dan obat-obatan',
                'enabled' => true,
                'redirect_uris' => [
                    'http://localhost:3003/auth/callback',
                    'https://pharmacy.rs.id/auth/callback',
                ],
                'callback_url' => 'https://pharmacy.rs.id/auth/callback',
                'secret' => 'pharmacy_secret_key_abc',
                'logo_url' => null,
                'token_expiry' => 3600,
                'created_by' => $admin?->id,
            ],
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
