<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Tenant\Application\StoreContextService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El contexto de tienda debe ser único por request para que el
        // global scope `BelongsToStore` y el middleware vean la misma instancia.
        $this->app->singleton(StoreContextService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Laravel carga automáticamente los archivos ubicados directamente en:
         *
         * database/migrations/*.php
         *
         * Este bloque solo registra las subcarpetas modulares, por ejemplo:
         *
         * database/migrations/auth
         * database/migrations/catalog
         * database/migrations/customization
         *
         * Así mantenemos las migraciones organizadas por dominio sin perder
         * el comportamiento nativo de Laravel para las migraciones raíz.
         */
        foreach (
            glob(
                pattern: database_path(path: 'migrations/*'),
                flags: GLOB_ONLYDIR,
            ) ?: [] as $directory
        ) {
            // Permite que Laravel cargue migraciones desde esa subcarpeta
            $this->loadMigrationsFrom(paths: $directory);
        }
    }
}
