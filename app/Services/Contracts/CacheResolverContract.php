<?php

namespace App\Services\Contracts;

use App\Models\Application;
use App\Models\User;

interface CacheResolverContract
{
    /**
     * @return list<string>
     */
    public function rememberUserPerms(User $user, Application $application): array;

    public function invalidateUser(User $user, ?Application $application = null): void;
}
