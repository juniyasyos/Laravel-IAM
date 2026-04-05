<?php

namespace App\Console\Commands;

use App\Domain\Iam\Models\AccessProfile;
use App\Domain\Iam\Models\Application;
use App\Models\User;
use Illuminate\Console\Command;

class DebugUserSync extends Command
{
    protected $signature = 'debug:user-sync {--app-key= : Filter by application key} {--user-email= : Filter by user email}';

    protected $description = 'Debug user sync - show slug matching and profile assignments';

    public function handle()
    {
        $appKey = $this->option('app-key');
        $userEmail = $this->option('user-email');

        // 1. Application Roles
        $this->line("\n");
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('1️⃣  APPLICATION ROLES (iam_roles)');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('');

        $appQuery = Application::query();
        if ($appKey) {
            $appQuery->where('app_key', $appKey);
            $this->info("🔍 Filtering: app_key = \"$appKey\"");
        }
        $applications = $appQuery->with('roles')->get();

        if ($applications->isEmpty()) {
            $this->error('❌ No applications found' . ($appKey ? " with app_key=$appKey" : ""));
            return 1;
        }

        foreach ($applications as $app) {
            $status = $app->enabled ? '✅' : '❌';
            $this->line("<fg=cyan>📱 {$app->name} (app_key: {$app->app_key})</>");
            $this->line("   ID: {$app->id} | Enabled: {$status}");

            $roles = $app->roles()->get();
            if ($roles->isEmpty()) {
                $this->warn('   ⚠️  No roles defined');
            } else {
                $this->line('   Roles:');
                foreach ($roles as $role) {
                    $this->line("      • <fg=green>{$role->slug}</>");
                    $this->line("        Name: {$role->name}");
                    $this->line("        ID: {$role->id}");
                }
            }
            $this->line('');
        }

        // 2. Access Profiles & Role Links
        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('2️⃣  ACCESS PROFILES (access_profiles) & Role Links');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('');

        foreach ($applications as $app) {
            $this->line("<fg=cyan>📱 {$app->app_key}:</>");

            $profiles = AccessProfile::query()
                ->whereHas('roles', function ($q) use ($app) {
                    $q->where('application_id', $app->id);
                })
                ->with(['roles' => function ($q) use ($app) {
                    $q->where('application_id', $app->id);
                }])
                ->get();

            if ($profiles->isEmpty()) {
                $this->warn('   ⚠️  No access profiles link to this application\'s roles');
            } else {
                foreach ($profiles as $profile) {
                    $this->line("   <fg=yellow>📦 {$profile->name}</>");
                    $this->line("      Slug: <fg=cyan>{$profile->slug}</>");
                    $this->line("      ID: {$profile->id}");
                    $this->line("      Linked Roles (this app only):");

                    $linkedRoles = $profile->roles()
                        ->where('application_id', $app->id)
                        ->get();

                    if ($linkedRoles->isEmpty()) {
                        $this->line('         (none)');
                    } else {
                        foreach ($linkedRoles as $role) {
                            $this->line("         • <fg=green>{$role->slug}</>");
                        }
                    }
                    $this->line('');
                }
            }
        }

        // 3. Slug Matching Analysis
        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('3️⃣  SLUG MATCHING ANALYSIS');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('');

        foreach ($applications as $app) {
            $this->line("<fg=cyan>📱 {$app->app_key}:</>");

            $roles = $app->roles()->pluck('slug')->toArray();
            $rolesStr = empty($roles) ? '(none)' : implode(', ', array_map(fn($r) => "<fg=green>$r</>", $roles));
            $this->line("   ✓ Application Roles: $rolesStr");

            $profiles = AccessProfile::query()
                ->whereHas('roles', function ($q) use ($app) {
                    $q->where('application_id', $app->id);
                })
                ->with(['roles' => function ($q) use ($app) {
                    $q->where('application_id', $app->id);
                }])
                ->get();

            $coveredSlugs = [];
            foreach ($profiles as $profile) {
                foreach ($profile->roles as $role) {
                    if (!in_array($role->slug, $coveredSlugs)) {
                        $coveredSlugs[] = $role->slug;
                    }
                }
            }

            $coveredStr = empty($coveredSlugs) ? '(none)' : implode(', ', array_map(fn($s) => "<fg=green>$s</>", $coveredSlugs));
            $this->line("   ✓ Covered by Profiles: $coveredStr");

            $missing = array_diff($roles, $coveredSlugs);
            if (!empty($missing)) {
                $missingStr = implode(', ', array_map(fn($m) => "<fg=red>$m</>", $missing));
                $this->line("   <fg=red>❌ MISSING PROFILE COVERAGE: $missingStr</>");
                $this->line('      → Create AccessProfile or link roles to existing profile');
            } elseif (!empty($roles)) {
                $this->line('   <fg=green>✅ All roles have profile coverage</>');
            }

            $this->line('');
        }

        // 4. User Profile Assignments
        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('4️⃣  USER PROFILE ASSIGNMENTS (user_access_profiles)');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('');

        foreach ($applications as $app) {
            $this->line("<fg=cyan>📱 {$app->app_key}:</>");

            $userQuery = User::query()
                ->whereHas('accessProfiles.roles', function ($q) use ($app) {
                    $q->where('application_id', $app->id);
                });

            if ($userEmail) {
                $userQuery->where('email', $userEmail);
            }

            $users = $userQuery->with(['accessProfiles' => function ($q) use ($app) {
                $q->whereHas('roles', function ($q2) use ($app) {
                    $q2->where('application_id', $app->id);
                })->with(['roles' => function ($q3) use ($app) {
                    $q3->where('application_id', $app->id);
                }]);
            }])->get();

            if ($users->isEmpty()) {
                $this->info('   ℹ️  No users assigned to profiles for this app' . ($userEmail ? " with email=$userEmail" : ""));
            } else {
                foreach ($users as $user) {
                    $this->line("   <fg=cyan>👤 {$user->name}</>");
                    $this->line("      Email: {$user->email}");
                    $this->line("      NIP: {$user->nip}");

                    $profiles = $user->accessProfiles()
                        ->whereHas('roles', function ($q) use ($app) {
                            $q->where('application_id', $app->id);
                        })
                        ->with(['roles' => function ($q) use ($app) {
                            $q->where('application_id', $app->id);
                        }])
                        ->get();

                    if ($profiles->isEmpty()) {
                        $this->warn('      ⚠️  No profiles assigned');
                    } else {
                        $this->line('      <fg=yellow>📦 Assigned Profiles:</>');
                        foreach ($profiles as $profile) {
                            $roleSlugs = $profile->roles->pluck('slug')->toArray();
                            $rolesStr = implode(', ', array_map(fn($r) => "<fg=green>$r</>", $roleSlugs));
                            $this->line("         • {$profile->name} (slug: <fg=cyan>{$profile->slug}</>)");
                            $this->line("           Roles: $rolesStr");
                        }
                    }
                    $this->line('');
                }
            }
        }

        $this->line('');
        $this->line('✅ Debug report complete');
        $this->line('');
        $this->info('📝 How to use this output:');
        $this->line('   1. Check "SLUG MATCHING ANALYSIS" for ❌ MISSING PROFILE COVERAGE');
        $this->line('   2. Create AccessProfiles for missing slugs');
        $this->line('   3. Run user sync again');
        $this->line('   4. Check "USER PROFILE ASSIGNMENTS" to verify');
        $this->line('');
        $this->info('📖 Read USER-SYNC-DEBUG-GUIDE.md for detailed explanation');
        $this->line('');
        $this->line('📊 Useful commands:');
        $this->line('   php artisan debug:user-sync --app-key=my-app');
        $this->line('   php artisan debug:user-sync --user-email=john@example.com');
        $this->line('');

        return 0;
    }
}
