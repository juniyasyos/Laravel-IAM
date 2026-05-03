<?php

namespace App\Services;

use App\Exceptions\TtdNotFoundException;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class TtdUrlService
{
    public function generatePresignedUrl(User $user, int $expiresMinutes = 15): string
    {
        $path = trim((string) $user->ttd_url);

        if ($path === '') {
            throw new TtdNotFoundException('User does not have a TTD file configured.');
        }

        $path = $this->normalizePath($path);
        $disk = Storage::disk('s3');

        if (! $disk->exists($path)) {
            throw new TtdNotFoundException('TTD file not found in storage.');
        }

        return $disk->temporaryUrl($path, Carbon::now()->addMinutes($expiresMinutes));
    }

    private function normalizePath(string $path): string
    {
        if (str_contains($path, '://')) {
            $parsed = parse_url($path);
            if (! $parsed || empty($parsed['path'])) {
                return ltrim($path, '/');
            }

            $normalized = ltrim($parsed['path'], '/');
            $bucket = config('filesystems.disks.s3.bucket');

            if ($bucket !== null && str_starts_with($normalized, $bucket . '/')) {
                return substr($normalized, strlen($bucket) + 1);
            }

            return $normalized;
        }

        return ltrim($path, '/');
    }
}
