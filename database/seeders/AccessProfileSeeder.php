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
                'slug'        => 'kepala_unit',
                'name'        => 'Kepala Unit',
                'description' => 'Profil akses untuk kepala unit/instalasi di berbagai aplikasi.',
                'is_system'   => true,
            ],
            [
                'slug'        => 'tim_mutu',
                'name'        => 'Tim Mutu',
                'description' => 'Profil akses untuk tim mutu rumah sakit.',
                'is_system'   => true,
            ],
            [
                'slug'        => 'admin_mutu',
                'name'        => 'Admin Mutu',
                'description' => 'Profil akses admin aplikasi yang terkait mutu.',
                'is_system'   => true,
            ],
            [
                'slug'        => 'staf_unit',
                'name'        => 'Staf Unit',
                'description' => 'Profil akses staf/unit operasional.',
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
        // Contoh: kepala_unit => admin di siimut, viewer di tamasuma, dll.
        $mappings = [
            'kepala_unit' => [
                'siimut' => ['admin'],
                'tamasuma' => ['viewer'],
            ],
            'tim_mutu' => [
                'siimut' => ['viewer'],
                'incident-report.app' => ['viewer'],
            ],
            'admin_mutu' => [
                'siimut' => ['admin'],
                'tamasuma' => ['manager'],
            ],
            'staf_unit' => [
                'siimut' => ['receptionist'],
                'tamasuma' => ['staff'],
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
                $this->command->info("  ✅ Mapped ".count($roleIds)." role(s) to profile {$profile->name}");
            }
        }
    }
}
