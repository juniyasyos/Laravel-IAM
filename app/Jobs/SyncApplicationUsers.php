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

    /**
     * Optional application instance when the job was triggered from an
     * application row.  If provided the sync will be restricted to that app.
     */
    public ?Application $application = null;

    /**
     * Optional list of application IDs to sync.
     *
     * @var array<int>
     */
    public array $applicationIds = [];

    /**
     * Profile IDs selected by the admin.  When non‑empty the job will only
     * process the applications covered by these bundles and the sync service
     * will restrict profile attachments accordingly.
     *
     * @var array<int>
     */
    public array $profileIds = [];

    /**
     * Sinkronisasi manual atau otomatis.
     */
    public string $syncMode = 'auto';

    /**
     * Custom mapping (aplikasi => role slugs) untuk mode manual.
     *
     * @var array<int,array<string>>
     */
    public array $manualRoleMapping = [];

    /**
     * Accept either an array of profile IDs or an Application followed by
     * profile IDs, plus optionally application IDs.
     *
     * Examples:
     *   SyncApplicationUsers::dispatch([]);              // all-app sync
     *   SyncApplicationUsers::dispatch($app, $ids);      // single-app sync
     *   SyncApplicationUsers::dispatch($ids);            // role-bundle filter all apps
     *   SyncApplicationUsers::dispatch($appIds, $profileIds); // selected apps + bundles
     */
    public function __construct(array|Application $first = [], array $profileIds = [], array $applicationIds = [])
    {
        if ($first instanceof Application) {
            $this->application = $first;
            $this->profileIds = $profileIds;
            $this->applicationIds = $applicationIds;
            return;
        }

        if (empty($profileIds) && empty($applicationIds)) {
            // legacy: first arg is profileIds
            $this->profileIds = $first;
            return;
        }

        // new invocation: first is applicationIds, second is profileIds
        $this->applicationIds = $first;
        $this->profileIds = $profileIds;
    }

    public function handle(): void
    {
        // determine which apps should be synced
        $appsQuery = Application::query();

        if ($this->application) {
            $appsQuery->where('id', $this->application->id);
        } elseif (! empty($this->applicationIds)) {
            $appsQuery->whereIn('id', $this->applicationIds);
        }

        if (! empty($this->profileIds)) {
            // when the job is restricted to a set of access profiles we only
            // want applications that define roles included in those bundles.
            // capture the array in the closure to avoid relying on `$this`.
            $profileIds = $this->profileIds;

            $appsQuery->whereHas('roles.accessProfiles', function ($q) use ($profileIds) {
                // qualify the column name to prevent Laravel from confusing it
                // with any `application_id` fields that may be joined later.
                $q->whereIn('access_profiles.id', $profileIds);
            });
        }

        $appsQuery->get()->each(function (Application $app) {
            $service = new ApplicationUserSyncService(
                allowedProfileIds: $this->profileIds,
                syncMode: $this->syncMode,
                manualRoleMapping: $this->manualRoleMapping,
            );

            try {
                $result = $service->syncUsers($app);

                Log::info('application_user_sync_completed', [
                    'application_id' => $app->id,
                    'app_key' => $app->app_key,
                    'result' => $result,
                ]);
            } catch (\Exception $e) {
                Log::error('application_user_sync_failed', [
                    'application_id' => $app->id,
                    'app_key' => $app->app_key,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
