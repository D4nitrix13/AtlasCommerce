<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Metrics\MetricsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests del endpoint `/metrics` (RF-OBS-132 — Fase 13 PR3).
 *
 * Verifica:
 *  - Endpoint responde 200 con Content-Type Prometheus.
 *  - Body en formato Prometheus parseable.
 *  - HttpMetricsMiddleware NO registra /metrics, /up, /health/*
 *    (excluidos por path para evitar ruido en dashboards).
 *  - Headers de correlación (Fase 13 PR1) presentes en /metrics.
 */
#[Group('observability')]
#[Group('metrics')]
final class MetricsControllerTest extends TestCase
{
    #[Test]
    public function metrics_endpoint_returns_200_with_prometheus_content_type(): void
    {
        $response = $this->get(uri: '/metrics');

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function metrics_endpoint_returns_prometheus_format(): void
    {
        $response = $this->get(uri: '/metrics');

        $response->assertOk();
        $body = $response->getContent();

        // Prometheus format usa `# HELP` y `# TYPE` por métrica.
        $this->assertStringContainsString('# HELP', (string) $body);
        $this->assertStringContainsString('# TYPE', (string) $body);
    }

    #[Test]
    public function metrics_excludes_operational_paths(): void
    {
        // Generamos tráfico en paths excluidos y en uno de negocio.
        $this->getJson(uri: '/health/live');
        $this->getJson(uri: '/up');
        // No podemos fácilmente forzar /api/v1/... sin DB, pero la
        // intención queda cubierta: el path de /metrics, /up, /health/*
        // está excluido del HttpMetricsMiddleware.

        $response = $this->get(uri: '/metrics');
        $body = (string) $response->getContent();

        // /health/live y /up no deben aparecer como labels en
        // atlascommerce_http_requests_total (porque el middleware los
        // excluye).
        $this->assertStringNotContainsString('route="health/live"', $body);
        $this->assertStringNotContainsString('route="up"', $body);
        $this->assertStringNotContainsString('route="metrics"', $body);
    }

    #[Test]
    public function metrics_includes_business_routes_when_recorded(): void
    {
        // Forzamos una métrica directa vía el service.
        $service = $this->app->make(MetricsService::class);
        $service->recordHttpRequest(method: 'POST', route: 'api/v1/orders', status: 201);

        $response = $this->get(uri: '/metrics');
        $body = (string) $response->getContent();

        $this->assertStringContainsString('route="api/v1/orders"', $body);
        $this->assertStringContainsString('status="201"', $body);
    }

    #[Test]
    public function metrics_endpoint_includes_correlation_headers(): void
    {
        $response = $this->withHeader(name: 'X-Request-Id', value: 'metrics-test-abc')
            ->get(uri: '/metrics');

        $response->assertOk();
        $this->assertSame('metrics-test-abc', $response->headers->get('X-Request-Id'));
        $this->assertNotNull($response->headers->get('traceparent'));
    }
}
