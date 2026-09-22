<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tracing\Tracer;
use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Middleware que instrumenta cada HTTP request con un span
 * OpenTelemetry. Crea un span por request, le agrega atributos
 * estándar HTTP (método, ruta, status code) y termina el span al
 * enviar la response.
 *
 * Relación con Fase 13 PR1 (correlation IDs):
 *  - El trace_id del span se propaga al LogContext (vía W3C
 *    traceparent que ya emite el CorrelationIdMiddleware). Logs
 *    estructurados y spans comparten el mismo trace_id.
 *
 * Si el exporter es noop (default en dev) este middleware no agrega
 * overhead — solo crea spans que se descartan inmediatamente.
 *
 * Excluye paths operacionales (/metrics, /up, /health/*) para no
 * contaminar los traces con scrapes.
 */
final class HttpTracingMiddleware
{
    public function __construct(private readonly Tracer $tracer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        if (in_array(needle: $path, haystack: ['metrics', 'up', 'health', 'health/live', 'health/ready'], strict: true)) {
            return $next($request);
        }

        $span = $this->tracer->start(
            name: sprintf('%s %s', $request->getMethod(), $request->route()?->uri() ?? $request->path()),
            attributes: [
                TraceAttributes::HTTP_REQUEST_METHOD => $request->getMethod(),
                TraceAttributes::URL_PATH => '/'.$request->path(),
                TraceAttributes::URL_SCHEME => $request->getScheme(),
                TraceAttributes::SERVER_ADDRESS => $request->getHost(),
                TraceAttributes::USER_AGENT_ORIGINAL => $request->userAgent() ?? 'unknown',
            ],
        );

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $span->recordException($e);
            $span->setStatusError($e->getMessage());
            $span->end();
            throw $e;
        }

        $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());
        if ($response->getStatusCode() >= 500) {
            $span->setStatusError(sprintf('HTTP %d', $response->getStatusCode()));
        }
        $span->end();

        return $response;
    }
}
