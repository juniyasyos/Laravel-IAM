<?php

namespace App\Actions;

use App\Domain\Iam\Models\AccessProfile;
use App\Models\User;
use App\Models\UnitKerja;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportUsersFromJsonAction
{
    /**
     * Import users from JSON data including access profiles and unit_kerjas relationships
     * 
     * The "roles" key in JSON will be mapped to AccessProfiles with matching slugs
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
        $accessProfilesNotFound = [];
        $unitKerjasNotFound = [];

        foreach ($data as $index => $userData) {
            try {
                DB::beginTransaction();

                $user = User::updateOrCreate(
                    ['id' => $userData['id'] ?? null],
                    $this->sanitizeUserData($userData)
                );

                if ($user->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }

                // Handle access profiles assignment (mapped from "roles" key)
                if (! empty($userData['roles']) && is_array($userData['roles'])) {
                    $this->syncAccessProfiles($user, $userData['roles'], $accessProfilesNotFound);
                }

                // Handle unit_kerjas assignment
                if (! empty($userData['unit_kerjas']) && is_array($userData['unit_kerjas'])) {
                    $this->syncUnitKerjas($user, $userData['unit_kerjas'], $unitKerjasNotFound);
                }

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                $failed++;
                $errors[] = [
                    'row' => $index + 1,
                    'nip' => $userData['nip'] ?? 'N/A',
                    'name' => $userData['name'] ?? 'N/A',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $result = [
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'total' => count($data),
            'errors' => $errors,
        ];

        // Add warnings if any access profiles or unit_kerjas were not found
        if (! empty($accessProfilesNotFound)) {
            $result['warnings'] = $result['warnings'] ?? [];
            $result['warnings']['access_profiles_not_found'] = array_unique($accessProfilesNotFound);
        }

        if (! empty($unitKerjasNotFound)) {
            $result['warnings'] = $result['warnings'] ?? [];
            $result['warnings']['unit_kerjas_not_found'] = array_unique($unitKerjasNotFound);
        }

        return $result;
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
            'password' => $data['password'] ?? bcrypt('rschjaya1234'),
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

    /**
     * Sync user access profiles
     * Maps the "roles" array from JSON to AccessProfiles with matching slugs
     *
     * @param User $user
     * @param array $rolesArray Array of role slugs (will be matched to AccessProfile slugs)
     * @param array &$notFound Reference to track access profiles not found
     * @return void
     */
    private function syncAccessProfiles(User $user, array $rolesArray, array &$notFound): void
    {
        $accessProfileIds = [];

        foreach ($rolesArray as $roleSlug) {
            // Find AccessProfile by slug that matches the role key
            $accessProfile = AccessProfile::where('slug', $roleSlug)->first();

            if ($accessProfile) {
                $accessProfileIds[] = $accessProfile->id;
            } else {
                $notFound[] = $roleSlug;
            }
        }

        // Sync the found access profiles (this replaces existing relationships)
        $user->accessProfiles()->sync($accessProfileIds);
    }

    /**
     * Sync user unit_kerjas
     *
     * @param User $user
     * @param array $unitKerjas Array of unit_kerja data (can be id, name, or slug)
     * @param array &$notFound Reference to track unit_kerjas not found
     * @return void
     */
    private function syncUnitKerjas(User $user, array $unitKerjas, array &$notFound): void
    {
        $unitKerjaIds = [];

        foreach ($unitKerjas as $unitKerjaData) {
            $unitKerja = null;

            // Try to find by id first
            if (isset($unitKerjaData['id'])) {
                $unitKerja = UnitKerja::find($unitKerjaData['id']);
            }
            // Try to find by slug
            elseif (isset($unitKerjaData['slug'])) {
                $unitKerja = UnitKerja::where('slug', $unitKerjaData['slug'])->first();
            }
            // Try to find by name
            elseif (isset($unitKerjaData['unit_name'])) {
                $unitKerja = UnitKerja::where('unit_name', $unitKerjaData['unit_name'])->first();
            }
            // If it's just a string (id or slug), try both
            elseif (is_string($unitKerjaData)) {
                $unitKerja = UnitKerja::find($unitKerjaData)
                    ?? UnitKerja::where('slug', $unitKerjaData)->first();
            }

            if ($unitKerja) {
                $unitKerjaIds[] = $unitKerja->id;
            } else {
                $notFound[] = is_array($unitKerjaData)
                    ? ($unitKerjaData['slug'] ?? $unitKerjaData['unit_name'] ?? 'unknown')
                    : $unitKerjaData;
            }
        }

        // Sync the found unit_kerjas (this replaces existing relationships)
        $user->unitKerjas()->sync($unitKerjaIds);
    }
}
