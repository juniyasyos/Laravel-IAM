<?php

namespace App\Services\Contracts;

use App\Models\Application;
use App\Models\User;

interface ClaimsBuilderContract
{
    /**
     * Build claims for the provided user.
     *
     * @return array{sub:string,apps:list<string>,roles:list<string>,perms:list<string>,application_id?:int,app_key?:string}
     */
    public function build(User $user, ?Application $application = null): array;
}
