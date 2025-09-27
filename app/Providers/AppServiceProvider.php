<?php

namespace App\Providers;

use App\Events\PermissionSynced;
use App\Events\RoleAssigned as AppRoleAssigned;
use App\Events\RoleRevoked as AppRoleRevoked;
use App\Listeners\InvalidateUserRbacCache;
use App\Services\AppRegistry;
use App\Services\CacheResolver;
use App\Services\ClaimsBuilder;
use App\Services\Contracts\AppRegistryContract;
use App\Services\Contracts\CacheResolverContract;
use App\Services\Contracts\ClaimsBuilderContract;
use App\Services\Contracts\RbacServiceContract;
use App\Services\RbacService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AppRegistryContract::class, AppRegistry::class);
        $this->app->singleton(RbacServiceContract::class, RbacService::class);
        $this->app->singleton(CacheResolverContract::class, CacheResolver::class);
        $this->app->singleton(ClaimsBuilderContract::class, ClaimsBuilder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $listeners = [
            AppRoleAssigned::class,
            AppRoleRevoked::class,
            PermissionSynced::class,
            RoleAttached::class,
            RoleDetached::class,
            PermissionAttached::class,
            PermissionDetached::class,
        ];

        foreach ($listeners as $event) {
            Event::listen($event, [InvalidateUserRbacCache::class, 'handle']);
        }
    }
}
