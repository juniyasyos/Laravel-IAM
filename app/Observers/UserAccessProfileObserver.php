<?php

namespace App\Observers;

use App\Jobs\SyncApplicationUsers;
use App\Models\UserAccessProfile;
use Illuminate\Support\Facades\Log;

class UserAccessProfileObserver
{
    /**
     * Handle the UserAccessProfile "created" event.
     */
    public function created(UserAccessProfile $userAccessProfile): void
    {
        if (config('iam.user_sync_mode', 'pull') !== 'push') {
            return;
        }

        $user = $userAccessProfile->user;
        $profile = $userAccessProfile->accessProfile;

        Log::info('iam.user_access_profile_created', [
            'user_id' => $user?->id,
            'access_profile_id' => $profile?->id,
            'access_profile_name' => $profile?->name,
            'assigned_by' => $userAccessProfile->assigned_by,
            'timestamp' => now()->toDateTimeString(),
        ]);

        if ($user) {
            $this->dispatchSync($user, 'access_profile_assigned');
        }
    }

    /**
     * Handle the UserAccessProfile "updated" event.
     */
    public function updated(UserAccessProfile $userAccessProfile): void
    {
        if (config('iam.user_sync_mode', 'pull') !== 'push') {
            return;
        }

        $user = $userAccessProfile->user;
        $profile = $userAccessProfile->accessProfile;

        Log::info('iam.user_access_profile_updated', [
            'user_id' => $user?->id,
            'access_profile_id' => $profile?->id,
            'access_profile_name' => $profile?->name,
            'changed' => $userAccessProfile->getChanges(),
            'timestamp' => now()->toDateTimeString(),
        ]);

        if ($user) {
            $this->dispatchSync($user, 'access_profile_updated');
        }
    }

    /**
     * Handle the UserAccessProfile "deleted" event.
     */
    public function deleted(UserAccessProfile $userAccessProfile): void
    {
        if (config('iam.user_sync_mode', 'pull') !== 'push') {
            return;
        }

        $user = $userAccessProfile->user;
        $profile = $userAccessProfile->accessProfile;

        Log::warning('iam.user_access_profile_deleted', [
            'user_id' => $user?->id,
            'access_profile_id' => $profile?->id,
            'access_profile_name' => $profile?->name,
            'timestamp' => now()->toDateTimeString(),
        ]);

        if ($user) {
            $this->dispatchSync($user, 'access_profile_removed');
        }
    }

    /**
     * Handle the UserAccessProfile "restored" event.
     */
    public function restored(UserAccessProfile $userAccessProfile): void
    {
        if (config('iam.user_sync_mode', 'pull') !== 'push') {
            return;
        }

        $user = $userAccessProfile->user;
        $profile = $userAccessProfile->accessProfile;

        Log::info('iam.user_access_profile_restored', [
            'user_id' => $user?->id,
            'access_profile_id' => $profile?->id,
            'access_profile_name' => $profile?->name,
            'timestamp' => now()->toDateTimeString(),
        ]);

        if ($user) {
            $this->dispatchSync($user, 'access_profile_restored');
        }
    }

    /**
     * Dispatch sync job for the user.
     */
    protected function dispatchSync($user, string $event): void
    {
        Log::info('iam.user_access_profile_trigger_sync', [
            'user_id' => $user->id,
            'event' => $event,
            'email' => $user->email,
            'timestamp' => now()->toDateTimeString(),
        ]);

        SyncApplicationUsers::dispatch([], [], [], $user->id);
    }
}
