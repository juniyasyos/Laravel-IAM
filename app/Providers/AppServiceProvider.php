<?php

namespace App\Providers;

use App\Models\Session;
use App\Models\User;
use App\Observers\SessionObserver;
use App\Observers\UserApplicationRoleObserver;
use App\Observers\UserObserver;
use App\Services\AppRegistry;
use App\Services\Contracts\AppRegistryContract;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AppRegistryContract::class, AppRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Gate $gate): void
    {
        $gate->define('viewPulse', function (User $user) {
            return true;
        });

        User::observe(UserObserver::class);
        Session::observe(SessionObserver::class);
        \App\Domain\Iam\Models\UserApplicationRole::observe(UserApplicationRoleObserver::class);
        \App\Models\UserAccessProfile::observe(\App\Observers\UserAccessProfileObserver::class);
    }
}
