<?php

namespace Database\Seeders;

use App\Domain\Iam\Models\AccessProfile;
use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;
use Illuminate\Database\Seeder;

class AccessProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Daftar profil akses global yang umum di RS kamu
        $profiles = [
            [
                'slug'        => 'super_admin',
                'name'        => 'Super Admin',
                'description' => 'Profil akses penuh untuk super administrator sistem.',
                'is_system'   => true,
            ],
            [
                'slug'        => 'tim_mutu',
                'name'        => 'Tim Mutu',
                'description' => 'Profil akses untuk tim mutu rumah sakit.',
                'is_system'   => true,
            ],
            [
                'slug'        => 'unit_kerja',
                'name'        => 'Unit Kerja',
                'description' => 'Profil akses untuk unit kerja operasional.',
                'is_system'   => false,
            ],
            [
                'slug'        => 'admin_app',
                'name'        => 'Admin Aplikasi',
                'description' => 'Profil akses admin untuk aplikasi lainnya.',
                'is_system'   => false,
            ],
        ];

        foreach ($profiles as $profile) {
            AccessProfile::updateOrCreate(
                ['slug' => $profile['slug']],
                [
                    'name'        => $profile['name'],
                    'description' => $profile['description'],
                    'is_system'   => $profile['is_system'],
                    'is_active'   => true,
                ]
            );
        }

        // Mapping access profiles to application roles for clarity and maintainability.
        // super_admin => semua admin, tim_mutu => tim_mutu di siimut, unit_kerja => unit_kerja di siimut + admin di apps lain
        $mappings = [
            'super_admin' => [
                'siimut' => ['super_admin'],
                'incident-report.app' => ['admin'],
            ],
            'tim_mutu' => [
                'siimut' => ['tim_mutu'],
            ],
            'unit_kerja' => [
                'siimut' => ['unit_kerja'],
            ],
            'admin_app' => [
                'client-example' => ['admin'],
                'incident-report.app' => ['admin'],
            ],
        ];

        foreach ($mappings as $profileSlug => $apps) {
            $profile = AccessProfile::where('slug', $profileSlug)->first();

            if (! $profile) {
                $this->command->warn("⚠️  AccessProfile '{$profileSlug}' not found, skipping mapping.");

                continue;
            }

            $roleIds = [];

            foreach ($apps as $appKey => $roleSlugs) {
                $application = Application::where('app_key', $appKey)->first();

                if (! $application) {
                    $this->command->warn("⚠️  Application '{$appKey}' not found for mapping, skipping.");

                    continue;
                }

                foreach ($roleSlugs as $roleSlug) {
                    $role = ApplicationRole::where('application_id', $application->id)
                        ->where('slug', $roleSlug)
                        ->first();

                    if ($role) {
                        $roleIds[] = $role->id;
                    } else {
                        $this->command->warn("⚠️  Role '{$roleSlug}' for app '{$appKey}' not found while mapping.");
                    }
                }
            }

            if (! empty($roleIds)) {
                $profile->roles()->syncWithoutDetaching(array_unique($roleIds));
                $this->command->info("  ✅ Mapped " . count($roleIds) . " role(s) to profile {$profile->name}");
            }
        }
    }
}
