<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting IAM Database Seeding...');
        $this->command->newLine();

        // Order matters: Users -> Applications -> IAM Roles -> IAM User Role Assignments
        $this->call([
            UserSeeder::class,                    // Create users first
            ApplicationsSeeder::class,            // Create registered applications
            IamRolesSeeder::class,                // Create IAM roles per application
            AccessProfileSeeder::class,           // Create access profiles and map to roles
            IamUserRoleAssignmentsSeeder::class,  // Assign IAM roles to users directly
            UserAccessProfileSeeder::class,       // Assign access profiles to users
        ]);

        $this->command->newLine();
        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->newLine();

        // Display summary
        $this->displaySummary();
    }

    /**
     * Display seeding summary.
     */
    private function displaySummary(): void
    {
        $this->command->info('📊 Seeding Summary:');
        $this->command->newLine();

        $this->command->table(
            ['Resource', 'Count'],
            [
                ['Users', \App\Models\User::count()],
                ['Applications', \App\Domain\Iam\Models\Application::count()],
                ['IAM Roles', \App\Domain\Iam\Models\ApplicationRole::count()],
                ['Access Profiles', \App\Domain\Iam\Models\AccessProfile::count()],
                ['User Role Assignments', \App\Domain\Iam\Models\UserApplicationRole::count()],
                ['User Access Profiles', DB::table('user_access_profiles')->count()],
            ]
        );

        $this->command->newLine();
        $this->command->info('🔐 Sample Login Credentials:');
        $this->command->table(
            ['Email', 'Password', 'Apps Access'],
            [
                ['admin@gmail.com', 'password', '5 apps (all)'],
                ['doctor@gmail.com', 'password', 'client, siimut, incident'],
                ['nurse@gmail.com', 'password', 'client, siimut, incident'],
                ['manager@gmail.com', 'password', 'client, siimut, tamasuma, incident'],
                ['pharmacist@gmail.com', 'password', 'client, siimut, pharmacy', 'Pharmacy'],
                ['staff@gmail.com', 'password', 'client, siimut, tamasuma, incident', 'General'],
            ]
        );

        $this->command->newLine();
        $this->command->info('📱 Sample Application Credentials:');
        $this->command->table(
            ['App Key', 'Secret', 'Name', 'Roles Count'],
            [
                ['client-example', 'client_example_secret_789', 'Client Example', '2 roles'],
                ['siimut', 'siimut_secret_key_123', 'SIIMUT', '5 roles'],
                ['tamasuma', 'tamasuma_secret_key_456', 'Tamasuma', '4 roles'],
                ['incident-report.app', 'incident_secret_key_789', 'Incident Report', '4 roles'],
                ['pharmacy.app', 'pharmacy_secret_key_abc', 'Pharmacy', '4 roles'],
            ]
        );

        $this->command->newLine();
        $this->command->info('🎭 Role Structure Example:');
        $this->command->line('  admin@gmail.com has:');
        $this->command->line('    - client-example: [admin]');
        $this->command->line('    - siimut: [admin, viewer]');
        $this->command->line('    - tamasuma: [admin]');
        $this->command->line('    - incident-report.app: [admin]');
        $this->command->line('    - pharmacy.app: [admin]');

        $this->command->newLine();
        $this->command->info('🌐 Access the admin panel: http://localhost:8000/admin');
        $this->command->newLine();
    }
}
