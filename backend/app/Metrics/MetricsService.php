<?php

declare(strict_types=1);

namespace App\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;

/**
 * Fachada tipada sobre el `CollectorRegistry` de Prometheus.
 *
 * Centraliza las definiciones de métricas para que:
 *  - Cada métrica se registre UNA vez (idempotente).
 *  - Los HELP/TYPE/labels estén documentados en un solo lugar.
 *  - Los call sites usen métodos tipados en lugar de strings mágicos.
 *
 * Métricas expuestas (namespace configurable vía `config/metrics.php`):
 *  - `atlascommerce_http_requests_total` (counter) — total de requests.
 *    Labels: method, route, status.
 *  - `atlascommerce_http_request_duration_seconds` (histogram) — duración.
 *    Labels: method, route, status. Buckets configurables.
 *  - `atlascommerce_queue_size` (gauge) — tamaño actual de la cola.
 *    Labels: queue, connection.
 *
 * Métricas de negocio (counter):
 *  - `atlascommerce_business_events_total` — eventos discretos del dominio
 *    (orders.created, payments.completed, etc.). Labels: event, store_id.
 */
final class MetricsService
{
    public function __construct(private readonly CollectorRegistry $registry) {}

    /**
     * Incrementa el contador de requests HTTP.
     *
     * @param  string  $method  HTTP method (GET, POST, etc.).
     * @param  string  $route  URI normalizado (ej: /api/v1/products/{id}).
     * @param  int  $status  HTTP status code de la response.
     */
    public function recordHttpRequest(string $method, string $route, int $status): void
    {
        $this->httpRequestsCounter()->inc([
            'method' => $method,
            'route' => $route,
            'status' => (string) $status,
        ]);
    }

    /**
     * Observa la duración de un request HTTP en segundos.
     */
    public function recordHttpDuration(string $method, string $route, int $status, float $durationSeconds): void
    {
        $this->httpDurationHistogram()->observe(
            value: $durationSeconds,
            labels: [
                'method' => $method,
                'route' => $route,
                'status' => (string) $status,
            ],
        );
    }

    /**
     * Setea el tamaño actual de una cola (llamar periódicamente).
     */
    public function setQueueSize(string $connection, string $queue, int $size): void
    {
        $this->queueSizeGauge()->set(value: $size, labels: [
            'connection' => $connection,
            'queue' => $queue,
        ]);
    }

    /**
     * Registra un evento de negocio discreto.
     *
     * @param  string  $event  Nombre del evento (ej: "orders.created").
     * @param  string|null  $storeId  ID de tienda si aplica (null = global).
     */
    public function recordBusinessEvent(string $event, ?string $storeId = null): void
    {
        $this->businessEventsCounter()->inc([
            'event' => $event,
            'store' => $storeId ?? 'global',
        ]);
    }

    /**
     * Acceso directo al registry para casos especiales (render del
     * endpoint /metrics). Generalmente NO necesario desde la app —
     * usar los métodos tipados de arriba.
     */
    public function registry(): CollectorRegistry
    {
        return $this->registry;
    }

    private function httpRequestsCounter(): Counter
    {
        return $this->registry->getOrRegisterCounter(
            namespace: $this->namespace(),
            name: 'http_requests_total',
            help: 'Total HTTP requests processed by the application.',
            labels: ['method', 'route', 'status'],
        );
    }

    private function httpDurationHistogram(): Histogram
    {
        return $this->registry->getOrRegisterHistogram(
            namespace: $this->namespace(),
            name: 'http_request_duration_seconds',
            help: 'HTTP request duration in seconds.',
            labels: ['method', 'route', 'status'],
            buckets: config(key: 'metrics.http_duration_buckets', default: [0.1, 0.5, 1.0, 5.0]),
        );
    }

    private function queueSizeGauge(): Gauge
    {
        return $this->registry->getOrRegisterGauge(
            namespace: $this->namespace(),
            name: 'queue_size',
            help: 'Current size of a queue connection.',
            labels: ['connection', 'queue'],
        );
    }

    private function businessEventsCounter(): Counter
    {
        return $this->registry->getOrRegisterCounter(
            namespace: $this->namespace(),
            name: 'business_events_total',
            help: 'Total discrete business events.',
            labels: ['event', 'store'],
        );
    }

    private function namespace(): string
    {
        return (string) config(key: 'metrics.namespace', default: 'atlascommerce');
    }
}
