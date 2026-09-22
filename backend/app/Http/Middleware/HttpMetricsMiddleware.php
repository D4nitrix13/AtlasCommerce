<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Metrics\MetricsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Middleware que instrumenta cada HTTP request con métricas Prometheus:
 *  - `atlascommerce_http_requests_total` (counter, labels: method, route, status)
 *  - `atlascommerce_http_request_duration_seconds` (histogram, mismas labels)
 *
 * La `route` se obtiene del route binding de Laravel (`route.uri()` después
 * del dispatch) — usa placeholders (`/api/v1/products/{id}`) en lugar de
 * la URI real para evitar cardinalidad infinita en Prometheus.
 *
 * Si el request lanza una excepción, la métrica se registra con
 * status=500 igual — queremos ver errores en los dashboards.
 */
final class HttpMetricsMiddleware
{
    public function __construct(private readonly MetricsService $metrics) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Excluir endpoints operacionales del sampling — generan
        // ruido sin valor analítico en los dashboards.
        $path = $request->path();
        if (in_array(needle: $path, haystack: ['metrics', 'up', 'health', 'health/live', 'health/ready'], strict: true)) {
            return $next($request);
        }

        $start = hrtime(as_number: true);

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->record($request, status: 500, start: $start);
            throw $e;
        }

        $this->record($request, status: $response->getStatusCode(), start: $start);

        return $response;
    }

    private function record(Request $request, int $status, int|float $start): void
    {
        $method = $request->getMethod();
        // Normalizamos la ruta al patrón del route (con placeholders) — usar
        // la URI cruda causaría cardinalidad infinita en los dashboards.
        $route = $request->route()?->uri() ?? $request->path();
        $durationSeconds = (hrtime(as_number: true) - $start) / 1_000_000_000;

        $this->metrics->recordHttpRequest(method: $method, route: $route, status: $status);
        $this->metrics->recordHttpDuration(
            method: $method,
            route: $route,
            status: $status,
            durationSeconds: $durationSeconds,
        );
    }
}
