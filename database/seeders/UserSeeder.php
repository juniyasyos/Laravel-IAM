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

        // Remove header
        array_shift($lines);

        $processedCount = 0;
        $skippedCount = 0;
        $currentRecord = '';

        foreach ($lines as $line) {
            $currentRecord .= $line;

            // Try to parse the accumulated record
            $testParse = str_getcsv($currentRecord);

            // If we have at least 19 fields, process the record
            if (count($testParse) >= 19) {
                $data = $testParse;

                $nip = trim($data[2] ?? '');
                $name = trim($data[3] ?? '');
                $email = trim($data[9] ?? '');
                $active = trim($data[18] ?? '') === '1' || strtolower(trim($data[18] ?? '')) === 'true';

                // Skip if NIP or name is empty
                if (empty($nip) || empty($name)) {
                    $skippedCount++;
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
                    str_contains($email, 'SURABAYA') || str_contains($email, 'MALUKU') ||
                    str_contains($email, 'SITUBONDO') || str_contains($email, 'BANYUWANGI') ||
                    str_contains($email, 'BONDOWOSO') || str_contains($email, 'KEDIRI'))) {
                    $email = null;
                }

                // Use simple password for all users (except admin)
                $hashedPassword = $nip === '0000.00000'
                    ? Hash::make('adminpassword')
                    : Hash::make('rschjaya123');

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

                $processedCount++;
                $currentRecord = '';
            } else {
                // Continue accumulating
                $currentRecord .= "\n";
            }
        }

        Log::info('Berhasil memproses ' . $processedCount . ' data CSV, melewatkan ' . $skippedCount . ' record.');
    }
}
