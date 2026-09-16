<?php

declare(strict_types=1);

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\ResolveStoreFromDomain;
use App\Http\Middleware\SetSessionDomainFromHost;
use App\Http\Middleware\StoreMaintenanceMode;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CR2: prepended ANTES del session middleware para que el Domain
        // del cookie quede seteado antes de emitir el Set-Cookie.
        // EnsureFrontendRequestsAreStateful agrega StartSession + cookies
        // cuando el host está en SANCTUM_STATEFUL_DOMAINS; sin esto, los
        // requests a /api/* no reciben session cookies.
        $middleware->api(prepend: [
            SetSessionDomainFromHost::class,
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'store.domain' => ResolveStoreFromDomain::class,
            'store.maintenance' => StoreMaintenanceMode::class,
            'permission' => CheckPermission::class,
            // CR2: alias de la middleware de Sanctum para uso futuro en
            // rutas específicas si fuera necesario (ej: rutas con auth
            // vía cookie que no estén en el grupo api).
            'sanctum.stateful' => EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })
    ->withProviders([
        App\Providers\AppServiceProvider::class,
        App\Providers\ViewNamespaceServiceProvider::class,
    ])
    ->create();
