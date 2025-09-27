<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\User;
use App\Services\Contracts\AppRegistryContract;
use App\Services\Contracts\ClaimsBuilderContract;
use Illuminate\Console\Command;

class IamDumpClaims extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iam:dump-claims {--user= : The user ID} {--app= : Filter by application key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump computed IAM claims for a given user';

    /**
     * Execute the console command.
     */
    public function handle(ClaimsBuilderContract $claimsBuilder, AppRegistryContract $registry)
    {
        $userId = $this->option('user');

        if (! $userId || ! is_numeric($userId)) {
            $this->error('The --user option is required and must be numeric.');

            return self::FAILURE;
        }

        $user = User::query()->find($userId);

        if (! $user) {
            $this->error("User with ID {$userId} not found.");

            return self::FAILURE;
        }

        $appOption = $this->option('app');
        $application = null;

        if ($appOption) {
            /** @var Application $application */
            $application = $registry->getByKeyOrFail((string) $appOption);
        }

        $claims = $claimsBuilder->build($user, $application);

        $this->line(json_encode($claims, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
