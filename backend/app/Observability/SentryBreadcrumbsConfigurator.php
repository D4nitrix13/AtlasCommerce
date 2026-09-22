<?php

declare(strict_types=1);

namespace App\Observability;

use App\Logging\LogContext;
use Sentry\Breadcrumb;

/**
 * Configura breadcrumbs de Sentry con el contexto de correlación de
 * la request actual (RF-OBS-134 — Fase 13 PR5).
 *
 * Por qué breadcrumbs importan: cuando Sentry captura una excepción,
 * los breadcrumbs son la "historia" de lo que pasó ANTES. Si los
 * logs estructurados (Fase 13 PR1) tienen un request_id, y los
 * spans (Fase 13 PR4) tienen un trace_id, queremos que Sentry los
 * muestre también — para que desde el dashboard de Sentry podamos
 * saltar a Loki/Tempo con un clic.
 *
 * Esta clase se invoca vía `before_breadcrumb` configurado en
 * `config/sentry.php`. Solo agrega el breadcrumb si la request tiene
 * IDs de correlación activos (no contaminamos breadcrumbs de
 * jobs/console que no tienen request_id).
 *
 * API inmutable: Breadcrumb::withMetadata() retorna nueva instancia.
 */
final class SentryBreadcrumbsConfigurator
{
    public function __construct(private readonly LogContext $logContext) {}

    public function __invoke(Breadcrumb $breadcrumb): ?Breadcrumb
    {
        $requestId = $this->logContext->getRequestId();
        $traceId = $this->logContext->getTraceId();
        $spanId = $this->logContext->getSpanId();

        if ($requestId === null && $traceId === null && $spanId === null) {
            // Sin contexto de correlación — probablemente console/jobs.
            // Devolvemos el breadcrumb sin modificar para mantener
            // el comportamiento default de Sentry.
            return $breadcrumb;
        }

        if ($requestId !== null) {
            $breadcrumb = $breadcrumb->withMetadata(name: 'request_id', value: $requestId);
        }
        if ($traceId !== null) {
            $breadcrumb = $breadcrumb->withMetadata(name: 'trace_id', value: $traceId);
        }
        if ($spanId !== null) {
            $breadcrumb = $breadcrumb->withMetadata(name: 'span_id', value: $spanId);
        }

        return $breadcrumb;
    }
}
