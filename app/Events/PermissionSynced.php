<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Spatie\Permission\Models\Role;

class PermissionSynced
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public Role $role,
        public array $permissions,
        public Application $application
    ) {
    }
}
