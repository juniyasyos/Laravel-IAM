<?php

namespace App\Observers;

use App\Domain\Iam\Models\Application;
use Illuminate\Support\Facades\Log;

class ApplicationObserver
{
    /**
     * Handle the Application "updated" event.
     * OPTIMIZATION: Clear cache when application is updated
     */
    public function updated(Application $application): void
    {
        Log::info('iam.application_updated', [
            'application_id' => $application->id,
            'app_key' => $application->app_key,
            'changed' => $application->getChanges(),
        ]);

        // Clear cache for this application
        $application->clearAppCache();
    }

    /**
     * Handle the Application "deleted" event.
     * OPTIMIZATION: Clear cache when application is deleted
     */
    public function deleted(Application $application): void
    {
        Log::info('iam.application_deleted', [
            'application_id' => $application->id,
            'app_key' => $application->app_key,
        ]);

        // Clear cache for this application
        $application->clearAppCache();
    }

    /**
     * Handle the Application "restored" event.
     * OPTIMIZATION: Clear cache when application is restored
     */
    public function restored(Application $application): void
    {
        Log::info('iam.application_restored', [
            'application_id' => $application->id,
            'app_key' => $application->app_key,
        ]);

        // Clear cache for this application
        $application->clearAppCache();
    }
}
