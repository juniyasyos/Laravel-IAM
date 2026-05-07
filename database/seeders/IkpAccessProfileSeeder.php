<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Iam\Models\AccessProfile;
use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;

class IkpAccessProfileSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * 🔥 IKP-SPECIFIC SEEDER
         * Hanya seed access profiles dengan aplikasi 'ikp'
         */
        $configPath = config_path('access-profiles-ikp.json');
        $allMappings = json_decode(file_get_contents($configPath), true);

        /**
         * 🔥 Prefetch
         */
        $applications = Application::pluck('id', 'app_key');
        $roles = ApplicationRole::get()->groupBy('application_id');

        foreach ($allMappings as $map) {
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
             * ✅ Loop IKP App Only
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

                        // update cache
                        if ($roles->has($appId)) {
                            $roles[$appId]->push($role);
                        } else {
                            $roles[$appId] = collect([$role]);
                        }

                        $this->command->info("  ℹ️ Created role '{$roleData['slug']}' for app '{$appKey}'");
                    }

                    $roleIds[] = $role->id;
                }

                /**
                 * ✅ Sync Roles ke Profile (IKP only, preserve existing siimut roles)
                 */
                if (! empty($roleIds)) {
                    // Get siimut app ID
                    $siimutAppId = $applications['siimut'] ?? null;
                    $ikpAppId = $applications['incident-reporting'] ?? null;

                    // Get existing roles that are NOT from IKP
                    $existingRoleIds = $profile->roles()->pluck('iam_roles.id')->toArray();
                    $siimutRoleIds = [];

                    if ($siimutAppId) {
                        $siimutRoleIds = collect($existingRoleIds)
                            ->filter(function ($id) use ($roles, $siimutAppId) {
                                return $roles->get($siimutAppId, collect())->pluck('id')->contains($id);
                            })
                            ->toArray();
                    }

                    // Merge siimut roles with new ikp roles
                    $finalRoleIds = array_merge($siimutRoleIds, $roleIds);
                    $profile->roles()->sync($finalRoleIds);

                    $this->command->info(
                        "  ✅ Profile '{$profile->slug}' synced (" . count($roleIds) . " IKP roles)"
                    );
                }
            }
        }

        $this->command->info("✅ IKP Access Profile seeding completed!");
    }
}
