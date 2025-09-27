<?php

namespace App\Listeners;

use App\Events\PermissionSynced;
use App\Events\RoleAssigned as AppRoleAssigned;
use App\Events\RoleRevoked as AppRoleRevoked;
use App\Models\User;
use App\Services\Contracts\CacheResolverContract;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;
use Spatie\Permission\Models\Role;

class InvalidateUserRbacCache
{
    public function __construct(private readonly CacheResolverContract $cacheResolver)
    {
    }

    public function handle(object $event): void
    {
        if ($event instanceof AppRoleAssigned || $event instanceof AppRoleRevoked) {
            $this->cacheResolver->invalidateUser($event->user, $event->application);

            return;
        }

        if ($event instanceof PermissionSynced) {
            $event->role->users->each(function (User $user) use ($event): void {
                $this->cacheResolver->invalidateUser($user, $event->application);
            });

            return;
        }

        if ($event instanceof RoleAttached || $event instanceof RoleDetached) {
            if ($event->model instanceof User) {
                $this->cacheResolver->invalidateUser($event->model, null);
            }

            return;
        }

        if ($event instanceof PermissionAttached || $event instanceof PermissionDetached) {
            if ($event->model instanceof User) {
                $this->cacheResolver->invalidateUser($event->model, null);

                return;
            }

            if ($event->model instanceof Role) {
                $event->model->users->each(function (User $user): void {
                    $this->cacheResolver->invalidateUser($user, null);
                });
            }
        }
    }
}
