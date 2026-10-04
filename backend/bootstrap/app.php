<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CorrelationIdMiddleware;
use App\Http\Middleware\HttpMetricsMiddleware;
use App\Http\Middleware\HttpTracingMiddleware;
use App\Http\Middleware\ResolveStoreFromDomain;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetSessionDomainFromHost;
use App\Http\Middleware\StoreMaintenanceMode;
use App\Http\Middleware\ValidateStatefulCsrf;
use App\Http\Middleware\ValidateStoreIdHeader;
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

            // Web routes (welcome + health endpoints operacionales).
            // NOTA: cuando se usa `using:` closure en withRouting, los
            // parámetros `web:` y `health:` se IGNORAN silenciosamente
            // — Laravel solo corre la closure del usuario. Por eso los
            // cargamos explícitamente acá. Bug pre-existente detectado
            // en Fase 13 PR2: el Caddyfile referencia /up (PR #102,
            // RF-REL-064) pero la ruta nunca se registraba.
            Route::middleware('web')->group(__DIR__.'/../routes/web.php');

            // /up — alias histórico de liveness. El Caddyfile de
            // producción lo usa como health_uri; mantener para no
            // romper el SLA RF-REL-064 ya desplegado.
            Route::get('/up', [HealthController::class, 'live'])->name('up');
        },
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CorrelationId va al INICIO del stack global para que cualquier
        // middleware o handler que loguee tenga IDs de trazabilidad
        // disponibles desde el primer momento (RF-OBS-130 — Fase 13).
        $middleware->prepend(CorrelationIdMiddleware::class);

        // HttpMetrics: instrumenta cada request con Prometheus
        // (counter + histogram). Prepended al stack global, después
        // de CorrelationId, para que las métricas puedan incluir el
        // request_id en sus labels si hace falta en el futuro.
        // (RF-OBS-132 — Fase 13 PR3).
        $middleware->prepend(HttpMetricsMiddleware::class);

        // HttpTracing: instrumenta cada request con un span
        // OpenTelemetry. Prepended al stack global, después de
        // HttpMetrics, para que las trazas tengan contexto de
        // correlación de PR1 (CorrelationId emite W3C traceparent
        // que el span respeta como parent context).
        // (RF-OBS-133 — Fase 13 PR4).
        $middleware->prepend(HttpTracingMiddleware::class);

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

        // CR2: SetSessionDomainFromHost también en el web group. Sin
        // esto, /sanctum/csrf-cookie (que corre en web middleware) emite
        // la cookie XSRF-TOKEN con Domain = exact host (admin.panda-estampados.com)
        // porque SESSION_DOMAIN=null, pero el POST a /api/v1/* carga la
        // sesión del cookie Domain = .panda-estampados.com. Resultado:
        // DOS sesiones distintas, DOS tokens XSRF-TOKEN distintos,
        // y el frontend lee el del web mientras el backend valida el del
        // api → 419 CSRF token mismatch en el primer POST post-mount.
        // Prepended ANTES de StartSession para que el Domain quede
        // seteado antes de emitir el Set-Cookie.
        $middleware->web(prepend: [
            SetSessionDomainFromHost::class,
        ]);

        // SecurityHeaders: aplica a TODAS las responses (web + api).
        // Defense-in-depth — Caddyfile ya emite estos headers para el
        // frontend HTML; acá los re-aplicamos a responses API/HTML
        // del backend (RF-SEC-017 + RF-SEC-021).
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'store.domain' => ResolveStoreFromDomain::class,
            'validate.store_id' => ValidateStoreIdHeader::class,
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

        // OAuth: errores de OAuth devuelven JSON 400 (no redirect).
        // Defense-in-depth: el redirect endpoint corta temprano si no
        // hay session — ese corte debe devolver JSON claro, no redirect
        // a login (que confundiría al frontend).
        $exceptions->render(function (App\Exceptions\SocialAuth\OAuthException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(
                    data: ['message' => $e->getMessage()],
                    status: $e->getStatusCode(),
                );
            }
        });
    })
    ->withProviders([
        App\Providers\AppServiceProvider::class,
        App\Providers\ViewNamespaceServiceProvider::class,
    ])
    ->create();
