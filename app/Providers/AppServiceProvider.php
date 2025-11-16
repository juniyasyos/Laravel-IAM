<?php

namespace App\Providers;

use App\Models\User;
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
    }
}
