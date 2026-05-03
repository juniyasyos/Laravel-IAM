<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Collection;
use Throwable;

class ImportUsersFromJsonAction
{
    /**
     * Import users from JSON data
     *
     * @param array $data JSON data array
     * @return array Statistics of import operation
     */
    public function execute(array $data): array
    {
        $created = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        foreach ($data as $index => $userData) {
            try {
                $user = User::updateOrCreate(
                    ['id' => $userData['id'] ?? null],
                    $this->sanitizeUserData($userData)
                );

                if ($user->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (Throwable $e) {
                $failed++;
                $errors[] = [
                    'row' => $index + 1,
                    'nip' => $userData['nip'] ?? 'N/A',
                    'name' => $userData['name'] ?? 'N/A',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'total' => count($data),
            'errors' => $errors,
        ];
    }

    /**
     * Sanitize and validate user data
     */
    private function sanitizeUserData(array $data): array
    {
        return [
            'nip' => $data['nip'] ?? null,
            'name' => $data['name'] ?? 'No Name',
            'email' => $data['email'] ?? null,
            'password' => bcrypt('rschjaya1234'),
            'place_of_birth' => $data['place_of_birth'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'address_ktp' => $data['address_ktp'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'status' => $data['status'] ?? 'active',
            'avatar_url' => $data['avatar_url'] ?? null,
            'ttd_url' => $data['ttd_url'] ?? null,
            'email_verified_at' => $data['email_verified_at'] ?? null,
            'remember_token' => $data['remember_token'] ?? null,
        ];
    }
}
