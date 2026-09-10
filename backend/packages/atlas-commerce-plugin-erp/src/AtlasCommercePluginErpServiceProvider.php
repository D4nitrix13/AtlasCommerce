<?php

declare(strict_types=1);

namespace D4nitrix13\AtlasCommercePluginErp;

use Illuminate\Support\ServiceProvider;

final class AtlasCommercePluginErpServiceProvider extends ServiceProvider
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
            namespace: 'atlas-commerce-plugin-erp',
        );
        $this->publishes([
            __DIR__.'/../config/erp.php' => config_path('erp.php'),
        ], groups: 'atlas-commerce-plugin-erp-config');
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/atlas-commerce-plugin-erp'),
        ], groups: 'atlas-commerce-plugin-erp-views');
    }
}
