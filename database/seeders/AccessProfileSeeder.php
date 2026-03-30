<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Iam\Models\AccessProfile;
use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;

class AccessProfileSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * 🔥 SINGLE SOURCE OF TRUTH
         * Semua didefinisikan di sini:
         * - Access Profile
         * - App
         * - Roles (slug, name, description)
         */
        $mappings = [
            [
                'profile' => [
                    'slug' => 'super_admin',
                    'name' => 'Super Admin',
                    'description' => 'Memiliki hak akses penuh terhadap seluruh fitur dan konfigurasi sistem.',
                    'is_system' => true,
                ],
                'apps' => [
                    'siimut' => [
                        [
                            'slug' => 'super_admin',
                            'name' => 'Super Admin',
                            'description' => 'Hak penuh seluruh sistem',
                        ],
                    ],
                ],
            ],

            [
                'profile' => [
                    'slug' => 'tim_mutu',
                    'name' => 'Tim Mutu',
                    'description' => 'Mengelola dan evaluasi indikator mutu rumah sakit.',
                    'is_system' => true,
                ],
                'apps' => [
                    'siimut' => [
                        [
                            'slug' => 'tim_mutu',
                            'name' => 'Tim Mutu',
                            'description' => 'Fokus pada indikator mutu',
                        ],
                    ],
                ],
            ],

            [
                'profile' => [
                    'slug' => 'validator_pic',
                    'name' => 'Unit Kerja: PIC Indikator',
                    'description' => 'Validasi dan monitoring indikator unit kerja.',
                    'is_system' => false,
                ],
                'apps' => [
                    'siimut' => [
                        [
                            'slug' => 'validator_pic',
                            'name' => 'Validator PIC',
                            'description' => 'Validasi data indikator',
                        ],
                    ],
                ],
            ],

            [
                'profile' => [
                    'slug' => 'pengumpul_data',
                    'name' => 'Unit Kerja: Pengumpul Data',
                    'description' => 'Mengumpulkan dan input data operasional.',
                    'is_system' => false,
                ],
                'apps' => [
                    'siimut' => [
                        [
                            'slug' => 'pengumpul_data',
                            'name' => 'Pengumpul Data',
                            'description' => 'Input data indikator',
                        ],
                    ],
                ],
            ],
        ];

        /**
         * 🔥 Prefetch (biar hemat query)
         */
        $applications = Application::pluck('id', 'app_key');
        $roles = ApplicationRole::get()->groupBy('application_id');

        foreach ($mappings as $map) {
            /**
             * ✅ Upsert Access Profile
             */
            $profileData = $map['profile'];

            $profile = AccessProfile::updateOrCreate(
                ['slug' => $profileData['slug']],
                [
                    'name'        => $profileData['name'],
                    'description' => $profileData['description'],
                    'is_system'   => $profileData['is_system'],
                    'is_active'   => true,
                ]
            );

            $roleIds = [];

            /**
             * ✅ Loop Apps
             */
            foreach ($map['apps'] as $appKey => $roleConfigs) {
                $appId = $applications[$appKey] ?? null;

                if (! $appId) {
                    $this->command->warn("⚠️ Application '{$appKey}' not found");
                    continue;
                }

                $existingRoles = $roles->get($appId, collect());

                /**
                 * ✅ Loop Roles
                 */
                foreach ($roleConfigs as $roleData) {
                    if (! is_array($roleData) || empty($roleData['slug'])) {
                        $this->command->warn("⚠️ Invalid role config for app '{$appKey}': expected array with slug");
                        continue;
                    }

                    $role = $existingRoles->firstWhere('slug', $roleData['slug']);

                    if (! $role) {
                        $role = ApplicationRole::create([
                            'application_id' => $appId,
                            'slug' => $roleData['slug'],
                            'name' => $roleData['name'] ?? ucfirst(str_replace(['_', '-'], ' ', $roleData['slug'])),
                            'description' => $roleData['description'] ?? 'Akses peran yang diatur oleh IAM',
                            'is_system' => false,
                        ]);

                        // update cache (penting biar gak miss di loop berikutnya)
                        if ($roles->has($appId)) {
                            $roles[$appId]->push($role);
                        } else {
                            $roles[$appId] = collect([$role]);
                        }

                        $this->command->info("  ℹ️ Created role '{$roleData['slug']}' for app '{$appKey}'");
                    }

                    $roleIds[] = $role->id;
                }
            }

            /**
             * ✅ Sync Roles ke Profile
             */
            if (! empty($roleIds)) {
                $profile->roles()->syncWithoutDetaching($roleIds);

                $this->command->info(
                    "  ✅ Profile '{$profile->slug}' synced (" . count($roleIds) . " roles)"
                );
            }
        }
    }
}
