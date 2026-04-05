<?php

namespace App\Console\Commands;

use App\Domain\Iam\Models\Application;
use App\Models\User;
use Illuminate\Console\Command;

class AssignTestUsers extends Command
{
    protected $signature = 'test:assign-users {--sample : Assign only first 5 users for testing} {--clear : Clear all assignments first}';

    protected $description = 'Assign all users to access profiles for testing';

    public function handle()
    {
        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('🔗 Assigning Users to Access Profiles');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('');

        $app = Application::where('app_key', 'siimut')->first();
        if (!$app) {
            $this->error('❌ Application siimut not found');
            return 1;
        }

        if ($this->option('clear')) {
            $this->info('🗑️  Clearing all user-profile assignments...');
            \DB::table('user_access_profiles')->truncate();
            $this->info('   ✅ Done');
            $this->line('');
        }

        // Get profiles
        $profiles = $app->roles()
            ->with('accessProfiles')
            ->get()
            ->flatMap(fn($role) => $role->accessProfiles)
            ->unique('id');

        if ($profiles->isEmpty()) {
            $this->error('❌ No access profiles found for siimut app');
            return 1;
        }

        $this->info("📦 Found " . $profiles->count() . " access profile(s):");
        foreach ($profiles as $p) {
            $this->info("   • {$p->name} (slug: {$p->slug})");
        }
        $this->line('');

        // Get users
        $userQuery = User::query();
        if ($this->option('sample')) {
            $userQuery->limit(5);
            $this->info('📝 Using SAMPLE mode: first 5 users only');
        } else {
            $this->info('📝 Using ALL mode: assigning all users');
        }

        $users = $userQuery->get();
        $this->info("👥 Found " . $users->count() . " user(s)");
        $this->line('');

        // Assign users to profiles
        $this->info('🔗 Assigning users to profiles...');
        $count = 0;

        foreach ($users as $user) {
            // Assign to all profiles (simple round-robin could work too)
            // For test, just assign to first profile
            $profile = $profiles->first();

            \DB::table('user_access_profiles')->updateOrInsert(
                ['user_id' => $user->id, 'access_profile_id' => $profile->id],
                ['assigned_by' => null, 'created_at' => now(), 'updated_at' => now()]
            );

            $count++;

            if ($count % 10 == 0) {
                $this->info("   Processing... {$count}/{$users->count()}");
            }
        }

        $this->line('');
        $this->info("✅ Assigned {$count} users to profile '{$profiles->first()->name}'");
        $this->line('');

        // Verify
        $this->info('📊 Verification:');
        $assigned = \DB::table('user_access_profiles')
            ->select('user_id', 'access_profile_id')
            ->distinct()
            ->count();
        $this->info("   Total user-profile links: {$assigned}");

        foreach ($profiles as $profile) {
            $count = \DB::table('user_access_profiles')
                ->where('access_profile_id', $profile->id)
                ->count();
            $this->info("   • Profile '{$profile->name}': {$count} users");
        }

        $this->line('');
        $this->info('✅ Ready for sync test!');
        $this->line('   Run: php artisan test:user-sync');
        $this->line('');

        return 0;
    }
}
