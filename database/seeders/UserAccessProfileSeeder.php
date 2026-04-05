<?php

namespace Database\Seeders;

use App\Domain\Iam\Models\AccessProfile;
use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserAccessProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('👤 Assigning Access Profiles to Users from CSV...');

        $assignments = $this->getAssignmentsFromCSV();

        if (empty($assignments)) {
            $this->command->warn('⚠️  No assignments found in CSV file');
            return;
        }

        $assignedCount = 0;
        $skippedCount = 0;

        foreach ($assignments as $assignment) {
            $user = User::where('nip', $assignment['nip'])->first();

            if (! $user) {
                $this->command->warn("⚠️  User with NIP '{$assignment['nip']}' not found, skipping.");
                $skippedCount++;
                continue;
            }

            if (empty($assignment['role_slugs'])) {
                $this->command->warn("⚠️  No role slugs for user {$user->name} (NIP: {$assignment['nip']}), skipping.");
                $skippedCount++;
                continue;
            }

            // Get access profiles for the role slugs
            $profileIds = $this->getProfileIdsForRoleSlugs($assignment['role_slugs']);

            if (!empty($profileIds)) {
                $user->accessProfiles()->sync($profileIds);
                $this->command->info("  ✅ Assigned " . count($profileIds) . " profile(s) to {$user->name} ({$assignment['nip']})");
                $assignedCount++;
            } else {
                $this->command->warn("⚠️  No access profiles found for roles: " . implode(', ', $assignment['role_slugs']));
                $skippedCount++;
            }
        }

        $this->command->newLine();
        $this->command->info("✅ User access profile assignments completed!");
        $this->command->info("   Assigned: {$assignedCount} users");
        $this->command->info("   Skipped: {$skippedCount} users");
    }

    /**
     * Get user access profile assignments from CSV file.
     */
    private function getAssignmentsFromCSV(): array
    {
        $csvFile = database_path('users_from_client.csv');

        if (!file_exists($csvFile)) {
            $this->command->warn("⚠️  CSV file not found at: {$csvFile}");
            return [];
        }

        $assignments = [];
        $handle = fopen($csvFile, 'r');

        if ($handle === false) {
            $this->command->warn("⚠️  Could not open CSV file: {$csvFile}");
            return [];
        }

        // Skip header row
        $header = fgetcsv($handle);

        // Find column indices
        $nipIndex = array_search('NIP', $header);
        $nameIndex = array_search('Name', $header);
        $emailIndex = array_search('Email', $header);
        $activeIndex = array_search('Active', $header);
        $roleSlugsIndex = array_search('Role Slugs', $header);

        if ($nipIndex === false || $roleSlugsIndex === false) {
            $this->command->warn("⚠️  CSV header missing required columns (NIP, Role Slugs)");
            fclose($handle);
            return [];
        }

        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (empty($row[$nipIndex])) {
                continue;
            }

            // Parse role slugs (pipe-separated)
            $roleSlugsStr = trim($row[$roleSlugsIndex] ?? '');
            $roleSlugs = array_filter(array_map('trim', explode('|', $roleSlugsStr)));

            $assignments[] = [
                'nip' => trim($row[$nipIndex]),
                'name' => $nameIndex !== false ? trim($row[$nameIndex]) : '',
                'email' => $emailIndex !== false ? trim($row[$emailIndex]) : '',
                'active' => $activeIndex !== false ? strtolower(trim($row[$activeIndex])) === 'true' : true,
                'role_slugs' => $roleSlugs,
            ];
        }

        fclose($handle);

        $this->command->info("  📄 Loaded " . count($assignments) . " user assignments from CSV");

        return $assignments;
    }

    /**
     * Get access profile IDs for given role slugs.
     */
    private function getProfileIdsForRoleSlugs(array $roleSlugs): array
    {
        if (empty($roleSlugs)) {
            return [];
        }

        $app = Application::where('app_key', 'siimut')->first();

        if (!$app) {
            $this->command->warn("⚠️  Application 'siimut' not found");
            return [];
        }

        // Get all application roles for the given role slugs
        $applicationRoles = ApplicationRole::where('application_id', $app->id)
            ->whereIn('slug', $roleSlugs)
            ->pluck('id')
            ->toArray();

        if (empty($applicationRoles)) {
            return [];
        }

        // Get access profiles linked to these roles
        $profileIds = AccessProfile::query()
            ->whereHas('roles', function ($q) use ($applicationRoles) {
                $q->whereIn('id', $applicationRoles);
            })
            ->pluck('id')
            ->toArray();

        return $profileIds;
    }
}
