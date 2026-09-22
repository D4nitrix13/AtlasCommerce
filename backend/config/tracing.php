<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tracing Configuration (RF-OBS-133 — Fase 13 PR4)
|--------------------------------------------------------------------------
|
| Configuración del sistema de tracing distribuido OpenTelemetry. Define
| el exporter (OTLP para producción, noop para dev), el nombre del
| servicio y el endpoint OTLP.
|
| Exporter:
|  - otlp (default si OTEL_EXPORTER_OTLP_ENDPOINT está set): envía
|    spans al collector OTLP vía gRPC/HTTP. Requiere un collector
|    corriendo (Tempo, Jaeger, SigNoz, etc).
|  - noop (default en dev): descarta los spans. Cero overhead.
|
| Para activar en producción: setear OTEL_EXPORTER_OTLP_ENDPOINT
| (ej: http://tempo:4317) y dejar el exporter=otlp.
|
| Relación con Fase 13 PR1 (correlation IDs):
|  - El HttpTracingMiddleware escribe el trace_id de OTel al
|    LogContext para que los logs estructurados compartan el
|    mismo trace_id con los spans — correlación perfecta entre
|    Loki y el backend de tracing.
*/

return [

    'enabled' => env(key: 'OTEL_ENABLED', default: false),

    'service_name' => env(key: 'OTEL_SERVICE_NAME', default: 'atlascommerce-backend'),

    'service_version' => env(key: 'OTEL_SERVICE_VERSION', default: '1.0.0'),

    'exporter' => env(key: 'OTEL_EXPORTER', default: 'noop'),

    'otlp' => [
        'endpoint' => env(key: 'OTEL_EXPORTER_OTLP_ENDPOINT', default: 'http://localhost:4317'),
        'protocol' => env(key: 'OTEL_EXPORTER_OTLP_PROTOCOL', default: 'grpc'),
        'timeout' => (int) env(key: 'OTEL_EXPORTER_OTLP_TIMEOUT', default: 10),
    ],

    // Sampler: en producción `parentbased_always_on`, en dev
    // `always_on` para ver todos los spans. Para sampling real,
    // considerar `parentbased_traceidratio` con ratio 0.1 (10%).
    'sampler' => env(key: 'OTEL_TRACES_SAMPLER', default: 'always_on'),
];
