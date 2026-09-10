<?php

declare(strict_types=1);

namespace D4nitrix13\EcommercePluginErp;

use Illuminate\Support\ServiceProvider;

final class EcommercePluginErpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__.'/../config/erp.php',
            key: 'erp',
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(path: __DIR__.'/../routes/api.php');
        $this->loadMigrationsFrom(paths: __DIR__.'/../database/migrations');
        $this->loadViewsFrom(
            path: __DIR__.'/../resources/views',
            namespace: 'ecommerce-plugin-erp',
        );
        $this->publishes([
            __DIR__.'/../config/erp.php' => config_path('erp.php'),
        ], groups: 'ecommerce-plugin-erp-config');
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/ecommerce-plugin-erp'),
        ], groups: 'ecommerce-plugin-erp-views');
    }
}
