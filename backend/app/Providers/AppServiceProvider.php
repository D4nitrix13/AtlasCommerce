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
        // Recorre todas las subcarpetas dentro de database/migrations
        foreach (
            glob(
                pattern: database_path(path: "migrations/*"),
                flags: GLOB_ONLYDIR
            ) ?: [] as $directory
        ) {
            // Permite que Laravel cargue migraciones desde esa subcarpeta
            $this->loadMigrationsFrom(paths: $directory);
        }
    }
}
