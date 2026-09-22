<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Metrics Configuration (RF-OBS-132 — Fase 13 PR3)
|--------------------------------------------------------------------------
|
| Configuración del sistema de métricas Prometheus. Define el backend
| de almacenamiento (Redis para producción, APCu o InMemory para dev),
| el namespace de las métricas, y los buckets por defecto para los
| histogramas de duración HTTP.
|
| Storage backends:
|  - redis (default): usa Predis para escribir en Redis. Es el ÚNICO
|    backend que agrega correctamente entre php-fpm workers.
|  - apcu: storage per-process vía APCu. Métricas NO agregadas entre
|    workers — solo útil para dev local con un solo proceso.
|  - inmemory: storage en proceso PHP. Métricas se pierden al final
|    de cada request. Solo útil para tests unitarios.
|
| CRÍTICO: en producción debe ser 'redis' para que /metrics muestre
| contadores agregados de toda la flota.
*/

return [

    'storage' => env(key: 'METRICS_STORAGE', default: 'redis'),

    'namespace' => env(key: 'METRICS_NAMESPACE', default: 'atlascommerce'),

    'redis' => [
        'host' => env(key: 'METRICS_REDIS_HOST', default: env(key: 'REDIS_HOST', default: '127.0.0.1')),
        'port' => (int) env(key: 'METRICS_REDIS_PORT', default: env(key: 'REDIS_PORT', default: 6379)),
        'password' => env(key: 'METRICS_REDIS_PASSWORD', default: env(key: 'REDIS_PASSWORD')),
        'database' => (int) env(key: 'METRICS_REDIS_DB', default: 0),
        'timeout' => (float) env(key: 'METRICS_REDIS_TIMEOUT', default: 0.1),
    ],

    // Buckets del histograma `http_request_duration_seconds`.
    // Cobertura: 5ms (cached) hasta 10s (timeout probable). Default
    // orientado a APIs web — ajustar si cambian los SLAs.
    'http_duration_buckets' => [
        0.005,
        0.01,
        0.025,
        0.05,
        0.1,
        0.25,
        0.5,
        1.0,
        2.5,
        5.0,
        10.0,
    ],
];
