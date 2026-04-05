<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the user's data into the database.
     */
    public function run(): void
    {
        $this->command->info('👥 Seeding users from CSV...');

        // Load users from CSV file
        $csvFile = database_path('users_from_client.csv');

        if (!file_exists($csvFile)) {
            $this->command->warn("⚠️  CSV file not found at: {$csvFile}");
            return;
        }

        $handle = fopen($csvFile, 'r');

        if ($handle === false) {
            $this->command->warn("⚠️  Could not open CSV file: {$csvFile}");
            return;
        }

        // Read header
        $header = fgetcsv($handle);

        // Find column indices
        $nipIndex = array_search('NIP', $header);
        $nameIndex = array_search('Name', $header);
        $emailIndex = array_search('Email', $header);
        $activeIndex = array_search('Active', $header);

        if ($nipIndex === false || $nameIndex === false) {
            $this->command->warn("⚠️  CSV header missing required columns (NIP, Name)");
            fclose($handle);
            return;
        }

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $nip = trim($row[$nipIndex] ?? '');
            $name = trim($row[$nameIndex] ?? '');
            $email = !empty($emailIndex) ? trim($row[$emailIndex] ?? '') : '';
            $active = !empty($activeIndex) ? strtolower(trim($row[$activeIndex] ?? 'true')) === 'true' : true;

            // Skip empty NIP or name
            if (empty($nip) || empty($name)) {
                $skippedCount++;
                continue;
            }

            // Set password based on NIP
            $password = $nip === '0000.00000'
                ? Hash::make('adminpassword')
                : Hash::make('rschjaya');

            // Use updateOrCreate to handle duplicates
            $result = User::updateOrCreate(
                ['nip' => $nip],
                [
                    'name' => $name,
                    'email' => !empty($email) ? $email : null,
                    'password' => $password,
                    'active' => $active,
                ]
            );

            if ($result->wasRecentlyCreated) {
                $createdCount++;
            } else {
                $updatedCount++;
            }
        }

        fclose($handle);

        $this->command->newLine();
        $this->command->info("✅ User seeding completed!");
        $this->command->info("   Created: {$createdCount} users");
        $this->command->info("   Updated: {$updatedCount} users");
        $this->command->info("   Skipped: {$skippedCount} records");
    }
}
