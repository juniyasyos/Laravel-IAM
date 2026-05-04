<?php

namespace App\Observers;

use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SessionObserver
{
    public function created(Session $session): void
    {
        Log::warning('session.observer.created', [
            'session_id' => $session->id,
            'user_id' => $session->user_id,
            'is_active' => $session->is_active,
            'last_activity' => $session->last_activity,
        ]);

        $this->updateUserLoginState($session);
    }

    public function updated(Session $session): void
    {
        if (! $session->wasChanged(['user_id', 'is_active'])) {
            Log::debug('session.observer.updated.ignored', [
                'session_id' => $session->id,
                'changed' => $session->getChanges(),
            ]);

            return;
        }

        Log::warning('session.observer.updated', [
            'session_id' => $session->id,
            'original_user_id' => $session->getOriginal('user_id'),
            'original_is_active' => $session->getOriginal('is_active'),
            'new_user_id' => $session->user_id,
            'new_is_active' => $session->is_active,
        ]);

        $this->updateUserLoginState($session, $session->getOriginal());
    }

    public function deleted(Session $session): void
    {
        if (! $session->user_id) {
            Log::info('session.deleted.no_user', [
                'session_id' => $session->id,
            ]);

            return;
        }

        $user = User::find($session->user_id);
        if (! $user) {
            Log::warning('session.deleted.user_not_found', [
                'session_id' => $session->id,
                'user_id' => $session->user_id,
            ]);

            return;
        }

        Log::info('session.deleted', [
            'session_id' => $session->id,
            'user_id' => $user->id,
            'user_nip' => $user->nip,
            'user_email' => $user->email,
        ]);

        // OPTIMIZATION: Clear user relationship caches when session is deleted
        $user->clearRelationshipCaches();
    }

    private function updateUserLoginState(Session $session, array $original = []): void
    {
        if (! $session->user_id) {
            return;
        }

        // OPTIMIZATION: Use only() to minimize memory footprint of loaded user
        $user = User::select(['id', 'nip', 'email'])->find($session->user_id);
        if (! $user) {
            Log::warning('session.observer.user_not_found', [
                'session_id' => $session->id,
                'user_id' => $session->user_id,
            ]);

            return;
        }

        if ($session->is_active) {
            Log::warning('session.observer.recording_login', [
                'session_id' => $session->id,
                'user_id' => $user->id,
            ]);

            // Re-fetch to get full user object for recording
            $fullUser = User::find($user->id);
            if ($fullUser) {
                $fullUser->recordLastLogin();
                // Clear the loaded relationship from memory
                $fullUser->clearRelationshipCaches();
            }

            return;
        }

        Log::warning('session.observer.recording_logout', [
            'session_id' => $session->id,
            'user_id' => $user->id,
        ]);

        // Re-fetch to get full user object for recording
        $fullUser = User::find($user->id);
        if ($fullUser) {
            $fullUser->recordLastLogout();
            // Clear the loaded relationship from memory
            $fullUser->clearRelationshipCaches();
        }
    }
}
