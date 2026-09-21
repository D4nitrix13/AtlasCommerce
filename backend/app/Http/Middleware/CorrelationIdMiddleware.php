<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Logging\LogContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que establece el contexto de correlación al inicio de cada
 * request y lo propaga al cliente en la response.
 *
 * Es la pieza Angular (entry point) del sistema de observabilidad:
 * garantiza que toda la instrumentación downstream (logs, errores,
 * futuros spans de OpenTelemetry, requests salientes) comparta los
 * mismos identificadores de trazabilidad.
 *
 * Comportamiento:
 *  1. Si la request trae `X-Request-Id` válido, lo conservamos como
 *     `request_id`. Si no, generamos uno nuevo (UUIDv7).
 *  2. Si trae `traceparent` W3C válido, propagamos `trace_id` y usamos
 *     el span del caller como `parent_span_id`. Si no, generamos trace
 *     nuevo.
 *  3. SIEMPRE generamos un `span_id` nuevo (este servicio es un hop
 *     nuevo del trace distribuido).
 *  4. Expone los IDs como atributos de la request para que
 *     controllers y servicios los lean sin tocar el container.
 *  5. Devuelve `X-Request-Id` y `traceparent` en la response para que
 *     el cliente y proxies intermedios puedan correlacionar.
 *
 * Registro: `bootstrap/app.php`, en el prepend de la api group (corre
 * ANTES de middlewares que puedan loguear).
 */
class CorrelationIdMiddleware
{
    public function __construct(private readonly LogContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->start(
            incomingTraceparent: $request->header(key: 'traceparent'),
            incomingRequestId: $request->header(key: 'X-Request-Id'),
        );

        $request->attributes->set(key: 'request_id', value: $this->context->getRequestId());
        $request->attributes->set(key: 'trace_id', value: $this->context->getTraceId());
        $request->attributes->set(key: 'span_id', value: $this->context->getSpanId());

        try {
            $response = $next($request);
        } finally {
            // Limpiamos el user/store del contexto al terminar la
            // request — Laravel reusa el container en testing/concurrency
            // y no queremos que un request "contamine" al siguiente.
            $this->context->setUserId(null);
            $this->context->setStoreId(null);
        }

        $response->headers->set(key: 'X-Request-Id', values: (string) $this->context->getRequestId());
        $response->headers->set(key: 'traceparent', values: $this->context->buildTraceparent());

        return $response;
    }
}
