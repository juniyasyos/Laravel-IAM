<?php

namespace App\Events;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleRevoked
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public string $role,
        public Application $application
    ) {
    }
}
