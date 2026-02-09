<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserSeeder extends Seeder
{
    /**
     * Seed the user's data into the database.
     */
    public function run(): void
    {
        // Create admin user if not exists
        if (!User::where('nip', '0000.00000')->exists()) {
            User::factory()->create([
                'nip' => '0000.00000',
                'name' => 'admin',
                'password' => Hash::make('adminpassword'),
                'active' => true,
            ]);
        }

        $filePath = database_path('users.csv');

        if (! File::exists($filePath)) {
            Log::warning('File "users.csv" tidak ditemukan di folder database.');

            return;
        }

        $csvContent = File::get($filePath);
        $lines = explode("\n", trim($csvContent));

        if (count($lines) < 2) {
            Log::warning('File CSV tidak memiliki data yang cukup.');
            return;
        }

        // Remove header row
        array_shift($lines);

        $usersToInsert = [];
        $currentRecord = '';
        $inAddressField = false;

        foreach ($lines as $line) {
            $currentRecord .= $line;

            // Count commas to determine if we have a complete record
            // We expect 19 fields (0-18)
            $commaCount = substr_count($currentRecord, ',');

            // If we have at least 19 commas, we might have a complete record
            // But we need to be careful about commas in quoted fields
            if ($commaCount >= 19 && !empty(trim($currentRecord))) {
                // Try to parse the record
                $fields = str_getcsv($currentRecord);

                if (count($fields) >= 19) {
                    $nip = trim($fields[2] ?? '');
                    $name = trim($fields[3] ?? '');
                    $email = trim($fields[9] ?? '');
                    $password = trim($fields[10] ?? '');
                    $active = trim($fields[18] ?? '') === '1' || strtolower(trim($fields[18] ?? '')) === 'true';

                    // Skip if NIP or name is empty
                    if (empty($nip) || empty($name)) {
                        $currentRecord = '';
                        continue;
                    }

                    // Skip if email contains address-like data
                    if (!empty($email) && (str_contains($email, 'DS.') || str_contains($email, 'JL.') ||
                        str_contains($email, 'DUSUN') || str_contains($email, 'RT.') ||
                        str_contains($email, 'RW.') || str_contains($email, 'NO.') ||
                        str_contains($email, 'BLOK') || str_contains($email, 'PERUM') ||
                        str_contains($email, 'LINGK.') || str_contains($email, 'KEL.') ||
                        str_contains($email, 'KEC.') || str_contains($email, 'KAB.') ||
                        str_contains($email, 'DESA') || str_contains($email, 'JEMBER') ||
                        str_contains($email, 'SURABAYA') || str_contains($email, 'MALUKU'))) {
                        $email = null;
                    }

                    // Skip if user with this NIP already exists
                    if (User::where('nip', $nip)->exists()) {
                        $currentRecord = '';
                        continue;
                    }

                    // Use existing password if it's hashed, otherwise hash it
                    $hashedPassword = strlen($password) === 60 && str_starts_with($password, '$2y$')
                        ? $password
                        : Hash::make($password);

                    // Use updateOrCreate to handle duplicates
                    User::updateOrCreate(
                        ['nip' => $nip],
                        [
                            'name' => $name,
                            'email' => $email,
                            'password' => $hashedPassword,
                            'active' => $active,
                            'updated_at' => now(),
                        ]
                    );

                    $currentRecord = '';
                } else {
                    // Not enough fields, continue accumulating
                    $currentRecord .= "\n";
                }
            } else {
                // Continue accumulating the record
                $currentRecord .= "\n";
            }
        }

        Log::info('Berhasil memproses data CSV.');
    }
}
