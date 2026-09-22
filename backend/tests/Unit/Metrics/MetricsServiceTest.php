<?php

declare(strict_types=1);

namespace Tests\Unit\Metrics;

use App\Metrics\MetricsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Tests\TestCase;

/**
 * Unit tests del `MetricsService` (RF-OBS-132 — Fase 13 PR3).
 *
 * Usa el adapter `InMemory` para no depender de Redis — los counters
 * se acumulan en el proceso, suficiente para verificar la lógica.
 *
 * Estrategia: renderiza el registry a texto Prometheus y verifica
 * substrings esperados. Es más robusto que iterar la estructura
 * interna del MetricFamilySamples (que tiene quirks por tipo de
 * métrica: counters, gauges, histograms con buckets).
 */
#[Group('observability')]
#[Group('metrics')]
final class MetricsServiceTest extends TestCase
{
    private MetricsService $service;

    private CollectorRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new CollectorRegistry(new InMemory);
        $this->service = new MetricsService($this->registry);
    }

    private function render(): string
    {
        $renderer = new \Prometheus\RenderTextFormat;

        return $renderer->render($this->registry->getMetricFamilySamples());
    }

    #[Test]
    public function it_records_http_request_counter(): void
    {
        $this->service->recordHttpRequest(method: 'GET', route: '/api/v1/products', status: 200);
        $this->service->recordHttpRequest(method: 'GET', route: '/api/v1/products', status: 200);
        $this->service->recordHttpRequest(method: 'GET', route: '/api/v1/products', status: 500);

        $body = $this->render();

        $this->assertStringContainsString('atlascommerce_http_requests_total', $body);
        $this->assertStringContainsString('method="GET",route="/api/v1/products",status="200"', $body);
        $this->assertStringContainsString('method="GET",route="/api/v1/products",status="500"', $body);
        // Counter para /products + status=200 debe valer 2.
        $this->assertMatchesRegularExpression(
            '/atlascommerce_http_requests_total\{method="GET",route="\/api\/v1\/products",status="200"\} 2/',
            $body,
        );
    }

    #[Test]
    public function it_records_http_duration_histogram(): void
    {
        $this->service->recordHttpDuration('GET', '/api/v1/x', 200, 0.123);
        $this->service->recordHttpDuration('GET', '/api/v1/x', 200, 0.456);

        $body = $this->render();

        // El histograma emite count y sum; verificamos que el count
        // aparece con valor 2 para esa combinación de labels.
        $this->assertMatchesRegularExpression(
            '/atlascommerce_http_request_duration_seconds_count\{method="GET",route="\/api\/v1\/x",status="200"\} 2/',
            $body,
        );
        // Y los buckets aparecen.
        $this->assertStringContainsString('atlascommerce_http_request_duration_seconds_bucket', $body);
    }

    #[Test]
    public function it_records_queue_size_gauge(): void
    {
        $this->service->setQueueSize(connection: 'database', queue: 'default', size: 42);

        $body = $this->render();

        $this->assertMatchesRegularExpression(
            '/atlascommerce_queue_size\{connection="database",queue="default"\} 42/',
            $body,
        );
    }

    #[Test]
    public function it_records_business_event_with_store_label(): void
    {
        $this->service->recordBusinessEvent(event: 'orders.created', storeId: '7');
        $this->service->recordBusinessEvent(event: 'orders.created', storeId: '7');

        $body = $this->render();

        $this->assertMatchesRegularExpression(
            '/atlascommerce_business_events_total\{event="orders\.created",store="7"\} 2/',
            $body,
        );
    }

    #[Test]
    public function it_records_business_event_without_store_as_global(): void
    {
        $this->service->recordBusinessEvent(event: 'system.startup');

        $body = $this->render();

        $this->assertMatchesRegularExpression(
            '/atlascommerce_business_events_total\{event="system\.startup",store="global"\} 1/',
            $body,
        );
    }

    #[Test]
    public function registry_is_exposed_for_rendering(): void
    {
        $this->assertSame($this->registry, $this->service->registry());
    }

    #[Test]
    public function registered_metrics_have_help_and_type(): void
    {
        $this->service->recordHttpRequest('GET', '/', 200);

        $body = $this->render();

        // Prometheus requiere # HELP y # TYPE por métrica.
        $this->assertStringContainsString('# HELP atlascommerce_http_requests_total', $body);
        $this->assertStringContainsString('# TYPE atlascommerce_http_requests_total counter', $body);
    }
}
