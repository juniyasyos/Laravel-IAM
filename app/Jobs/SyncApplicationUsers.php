<?php

namespace App\Jobs;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\ApplicationUserSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncApplicationUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Application $application) {}

    public function handle(): void
    {
        $service = new ApplicationUserSyncService();

        try {
            $result = $service->syncUsers($this->application);

            Log::info('application_user_sync_completed', [
                'application_id' => $this->application->id,
                'app_key' => $this->application->app_key,
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('application_user_sync_failed', [
                'application_id' => $this->application->id,
                'app_key' => $this->application->app_key,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
