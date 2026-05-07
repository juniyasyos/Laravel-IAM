<?php

namespace App\Support;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StorageFallback
{
    protected static ?bool $s3Available = null;

    /**
     * Cek apakah S3 (MinIO) dapat diakses dan bucket tersedia.
     */
    public static function isS3Available(bool $checkBucket = true): bool
    {
        if (static::$s3Available !== null) {
            return static::$s3Available;
        }

        try {
            $s3Config = config('filesystems.disks.s3');

            if (empty($s3Config['bucket'] ?? null)) {
                return false;
            }

            // Quick check through Storage facade (will throw on connection error)
            // but prefer explicit headBucket via AWS SDK for correctness.
            $client = new S3Client([
                'version' => 'latest',
                'region' => $s3Config['region'] ?? env('AWS_DEFAULT_REGION', 'us-east-1'),
                'endpoint' => $s3Config['endpoint'] ?? env('AWS_ENDPOINT'),
                'use_path_style_endpoint' => $s3Config['use_path_style_endpoint'] ?? env('AWS_USE_PATH_STYLE_ENDPOINT', false),
                'credentials' => [
                    'key' => $s3Config['key'] ?? env('AWS_ACCESS_KEY_ID'),
                    'secret' => $s3Config['secret'] ?? env('AWS_SECRET_ACCESS_KEY'),
                ],
                'http' => [
                    'timeout' => 2,
                    'connect_timeout' => 2,
                ],
            ]);

            if ($checkBucket) {
                $client->headBucket(['Bucket' => $s3Config['bucket']]);
            } else {
                // minimal list call to ensure connectivity
                $client->listBuckets();
            }

            return static::$s3Available = true;
        } catch (\Throwable $e) {
            // Log at debug level to avoid noisy logs in production
            Log::debug('S3 availability check failed: ' . $e->getMessage());

            return static::$s3Available = false;
        }
    }
}
