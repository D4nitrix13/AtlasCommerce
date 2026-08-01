<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (glob(database_path('migrations/*'), GLOB_ONLYDIR) ?: [] as $directory) {
            $this->loadMigrationsFrom($directory);
        }
    }
}
