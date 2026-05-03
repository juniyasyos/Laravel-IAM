<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class CheckMinioCommand extends Command
{
    protected $signature = 'minio:check {disk=s3 : The filesystem disk to check} {--quick : Validate configuration only, do not attempt network connection}';

    protected $description = 'Check whether MinIO / S3 storage is configured correctly and reachable.';

    public function handle(): int
    {
        $disk = $this->argument('disk');
        $quick = $this->option('quick');

        $this->info("Checking filesystem disk: {$disk}");

        $diskConfig = Config::get("filesystems.disks.{$disk}");

        if (! $diskConfig) {
            $this->error("Disk '{$disk}' is not configured in config/filesystems.php.");
            return self::FAILURE;
        }

        if (($diskConfig['driver'] ?? null) !== 's3') {
            $this->warn("The '{$disk}' disk is configured using driver '{$diskConfig['driver']}'. MinIO requires an S3-compatible disk.");
        }

        $required = [
            'key' => 'AWS_ACCESS_KEY_ID',
            'secret' => 'AWS_SECRET_ACCESS_KEY',
            'region' => 'AWS_DEFAULT_REGION',
            'bucket' => 'AWS_BUCKET',
            'endpoint' => 'AWS_ENDPOINT',
        ];

        $missing = [];

        foreach ($required as $configKey => $envKey) {
            $value = $diskConfig[$configKey] ?? Config::get('filesystems.disks.' . $disk . '.' . $configKey);
            if (! $value) {
                $missing[] = $envKey;
            }
        }

        if (! empty($missing)) {
            $this->error('Missing required MinIO/S3 configuration values:');
            foreach ($missing as $envKey) {
                $this->line("  - {$envKey}");
            }
            $this->line('Set them in your .env file or filesystem configuration, then try again.');
            return self::FAILURE;
        }

        $endpoint = $diskConfig['endpoint'] ?? Config::get("filesystems.disks.{$disk}.endpoint");
        $isMinio = str_contains($endpoint, 'minio') || str_contains($endpoint, 'localhost') || str_contains($endpoint, '127.0.0.1');

        $this->info('Configuration check passed.');
        $this->line("Endpoint: {$endpoint}");
        $this->line('MinIO-style endpoint: ' . ($isMinio ? '<fg=green>yes</>' : '<fg=yellow>unknown</>'));
        $this->line('Quick mode: ' . ($quick ? '<fg=yellow>enabled</>' : '<fg=green>disabled</>'));

        if ($quick) {
            $this->info('✅ Configuration is valid for MinIO / S3 storage.');
            return self::SUCCESS;
        }

        return $this->testConnection($disk);
    }

    private function testConnection(string $disk): int
    {
        $this->info('Testing connection to MinIO / S3...');

        try {
            $filesystem = Storage::disk($disk);
            $driver = $filesystem->getDriver();

            if (method_exists($driver, 'listContents')) {
                $driver->listContents('/', false);
            } else {
                $filesystem->exists('');
            }

            $this->info('✅ MinIO / S3 connection is working.');
            return self::SUCCESS;
        } catch (Exception $exception) {
            $this->error('❌ Failed to connect to MinIO / S3 storage.');
            $this->error($exception->getMessage());
            $this->line('Please verify AWS_* environment variables, endpoint URL, region, bucket name, and network access.');
            return self::FAILURE;
        }
    }
}
