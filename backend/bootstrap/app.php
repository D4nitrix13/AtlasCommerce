<?php

declare(strict_types=1);

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\ResolveStoreFromDomain;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetSessionDomainFromHost;
use App\Http\Middleware\StoreMaintenanceMode;
use App\Http\Middleware\ValidateStatefulCsrf;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        using: function (): void {
            // Versión actual: /api/v1/* (RF-MAIN-054).
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(__DIR__.'/../routes/api.php');
        },
        apiPrefix: '',
        web: __DIR__.'/../routes/web.php',
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
            // RF-SEC-018: CSRF para requests stateful. Skip si Bearer token.
            ValidateStatefulCsrf::class,
        ]);

        // SecurityHeaders: aplica a TODAS las responses (web + api).
        // Defense-in-depth — Caddyfile ya emite estos headers para el
        // frontend HTML; acá los re-aplicamos a responses API/HTML
        // del backend (RF-SEC-017 + RF-SEC-021).
        $middleware->append(SecurityHeaders::class);

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
