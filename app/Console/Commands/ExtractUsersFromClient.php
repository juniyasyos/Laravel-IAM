<?php

namespace App\Console\Commands;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\ApplicationUserSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExtractUsersFromClient extends Command
{
    protected $signature = 'extract:users-from-client {--app-key=siimut : Application key} {--output=database/users_from_client.csv : Output CSV file path}';

    protected $description = 'Extract users from client application and save to CSV (no sync to IAM yet)';

    public function handle()
    {
        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📤 Extracting Users from Client Application');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('');

        $appKey = $this->option('app-key');
        $outputPath = $this->option('output');

        // Step 1: Find Application
        $this->info('Step 1️⃣  Finding application...');
        $app = Application::where('app_key', $appKey)->first();

        if (!$app) {
            $this->error("❌ Application '{$appKey}' not found");
            return 1;
        }

        $this->info("   ✅ Found: {$app->name}");
        $this->info("   Callback URL: {$app->callback_url}");
        $this->line('');

        // Step 2: Fetch users from client
        $this->info('Step 2️⃣  Fetching users from client...');
        $service = new ApplicationUserSyncService();
        $result = $service->fetchClientUsers($app);

        if (!$result['success']) {
            $this->error("❌ Failed to fetch: {$result['error']}");
            return 1;
        }

        $clientUsers = $result['client_users'];
        $this->info("   ✅ Fetched " . count($clientUsers) . " users from client");
        $this->line('');

        // Step 3: Transform to CSV format
        $this->info('Step 3️⃣  Transforming to CSV format...');

        $csvData = [];
        $csvData[] = ['NIP', 'Name', 'Email', 'Active', 'Role Slugs']; // Header

        foreach ($clientUsers as $user) {
            $roles = $user['roles'] ?? [];
            $rolesStr = is_array($roles) ? implode('|', $roles) : $roles;

            $csvData[] = [
                $user['nip'] ?? '',
                $user['name'] ?? '',
                $user['email'] ?? '',
                $user['active'] ?? true ? 'true' : 'false',
                $rolesStr,
            ];
        }

        $this->info("   ✅ Prepared " . count($csvData) . " rows (including header)");
        $this->line('');

        // Step 4: Write to CSV
        $this->info('Step 4️⃣  Writing to CSV file...');

        // Ensure directory exists
        $dir = dirname(base_path($outputPath));
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        // Write CSV
        $handle = fopen(base_path($outputPath), 'w');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        $this->info("   ✅ Written to: {$outputPath}");
        $this->line('');

        // Step 5: Preview
        $this->info('Step 5️⃣  CSV Preview (first 10 rows):');
        $this->line('');

        $fileContent = File::get(base_path($outputPath));
        $lines = explode("\n", $fileContent);
        $count = 0;

        foreach ($lines as $line) {
            if (empty($line)) continue;
            if ($count < 11) {
                $this->line('   ' . $line);
                $count++;
            }
        }

        if (count($lines) > 12) {
            $this->line('   ... (' . (count($lines) - 2) . ' more rows)');
        }

        $this->line('');

        // Step 6: Summary
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('✅ Extraction Complete!');
        $this->line('');

        $this->table(
            ['Item', 'Value'],
            [
                ['Application', $app->app_key],
                ['Users Extracted', count($clientUsers)],
                ['Output File', $outputPath],
                ['File Size', File::size(base_path($outputPath)) . ' bytes'],
            ]
        );

        $this->line('');
        $this->info('📝 Next Steps:');
        $this->line('');
        $this->line('1. Review the CSV file:');
        $this->line("   cat {$outputPath}");
        $this->line('');
        $this->line('2. Check columns:');
        $this->line('   - NIP: User identifier');
        $this->line('   - Name: User full name');
        $this->line('   - Email: User email');
        $this->line('   - Active: true/false');
        $this->line('   - Role Slugs: separated by | (pipe)');
        $this->line('');
        $this->line('3. Once satisfied, import to IAM:');
        $this->line("   php artisan import:users-from-csv --file={$outputPath}");
        $this->line('');
        $this->line('4. Then sync to access profiles:');
        $this->line('   php artisan import:users-from-csv --file=' . $outputPath . ' --sync');
        $this->line('');

        return 0;
    }
}
