<?php

namespace Database\Seeders;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;
use Illuminate\Database\Seeder;

class IamRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎭 Seeding IAM Roles per Application...');

        $rolesData = $this->getRolesData();

        foreach ($rolesData as $appKey => $roles) {
            $application = Application::where('app_key', $appKey)->first();

            if (! $application) {
                $this->command->warn("⚠️  Application '{$appKey}' not found, skipping roles.");

                continue;
            }

            $this->command->info("  Creating roles for: {$application->name}");

            foreach ($roles as $roleData) {
                ApplicationRole::firstOrCreate(
                    [
                        'application_id' => $application->id,
                        'slug' => $roleData['slug'],
                    ],
                    [
                        'name' => $roleData['name'],
                        'description' => $roleData['description'],
                        'is_system' => $roleData['is_system'] ?? false,
                    ]
                );
            }

            $this->command->info("    ✅ Created " . count($roles) . ' roles');
        }

        $this->command->newLine();
        $this->command->info('✅ IAM Roles seeding completed!');
    }

    /**
     * Get roles data for each application.
     */
    private function getRolesData(): array
    {
        return [
            // 'siimut' => [
            //     [
            //         'slug' => 'super_admin',
            //         'name' => 'Super Admin SIIMUT',
            //         'description' => 'Full administrative access to SIIMUT system',
            //         'is_system' => true,
            //     ],
            //     [
            //         'slug' => 'tim_mutu',
            //         'name' => 'Tim Mutu',
            //         'description' => 'Tim Mutu dengan akses monitoring dan reporting',
            //         'is_system' => false,
            //     ],
            //     [
            //         'slug' => 'unit_kerja',
            //         'name' => 'Unit Kerja',
            //         'description' => 'Akses unit kerja untuk input data mutu',
            //         'is_system' => false,
            //     ],
            //     [
            //         'slug' => 'validator',
            //         'name' => 'Validator PIC',
            //         'description' => 'Akses validator untuk validasi data mutu',
            //         'is_system' => false,
            //     ],
            // ],

            // 'incident-report.app' => [
            //     [
            //         'slug' => 'admin',
            //         'name' => 'Administrator Incident Report',
            //         'description' => 'Full administrative access to incident reporting',
            //         'is_system' => true,
            //     ],
            // ],
        ];
    }
}
