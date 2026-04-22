<?php

namespace App\Observers;

use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SessionObserver
{
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
    }
}
