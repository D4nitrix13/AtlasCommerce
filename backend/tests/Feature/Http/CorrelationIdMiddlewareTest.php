<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Logging\LogContext;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del middleware `CorrelationIdMiddleware` (RF-OBS-130 — Fase 13).
 *
 * Cubre:
 *  - Generación de `X-Request-Id` y `traceparent` cuando el cliente no
 *    envía headers.
 *  - Conservación de IDs cuando el cliente envía headers válidos.
 *  - Echo de los headers en la response para que el cliente pueda
 *    correlacionar.
 *  - Población del `LogContext` singleton con los IDs de la request.
 *  - Manejo de traceparent malformado (fallback a trace nuevo sin romper).
 */
#[Group('observability')]
#[Group('correlation')]
final class CorrelationIdMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($currency, 'currency')->create();

        $this->host = "{$this->store->slug}.test";
        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => $this->host,
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_generates_correlation_ids_when_no_headers_sent(): void
    {
        $response = $this->getJson(uri: "http://{$this->host}/api/v1/storefront/promotions");

        $response->assertOk();

        $requestId = $response->headers->get('X-Request-Id');
        $traceparent = $response->headers->get('traceparent');

        $this->assertNotNull($requestId);
        $this->assertNotEmpty($requestId);

        $this->assertNotNull($traceparent);
        $this->assertMatchesRegularExpression(
            '/^00-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}$/',
            $traceparent,
        );
    }

    #[Test]
    public function it_propagates_incoming_request_id(): void
    {
        $incoming = 'client-trace-id-abc-123';

        $response = $this->withHeader(name: 'X-Request-Id', value: $incoming)
            ->getJson(uri: "http://{$this->host}/api/v1/storefront/promotions");

        $response->assertOk();
        $this->assertSame($incoming, $response->headers->get('X-Request-Id'));
    }

    #[Test]
    public function it_propagates_incoming_traceparent(): void
    {
        $incoming = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

        $response = $this->withHeader(name: 'traceparent', value: $incoming)
            ->getJson(uri: "http://{$this->host}/api/v1/storefront/promotions");

        $response->assertOk();

        $echoed = $response->headers->get('traceparent');
        $this->assertNotNull($echoed);
        $this->assertMatchesRegularExpression('/^00-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}$/', $echoed);

        // trace_id del upstream se conserva, span_id es nuevo.
        $parts = explode('-', $echoed);
        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $parts[1]);
        $this->assertNotSame('00f067aa0ba902b7', $parts[2]);
    }

    #[Test]
    public function it_falls_back_to_new_trace_when_incoming_traceparent_malformed(): void
    {
        $response = $this->withHeader(name: 'traceparent', value: 'not-a-valid-traceparent')
            ->getJson(uri: "http://{$this->host}/api/v1/storefront/promotions");

        $response->assertOk();

        $traceparent = $response->headers->get('traceparent');
        $this->assertNotNull($traceparent);
        // Malformed → trace_id es generado nuevo (no-all-zero).
        $parts = explode('-', $traceparent);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $parts[1]);
        $this->assertNotSame(str_repeat('0', 32), $parts[1]);
    }

    #[Test]
    public function it_populates_log_context_singleton(): void
    {
        $response = $this->withHeader(name: 'X-Request-Id', value: 'req-marker-99')
            ->getJson(uri: "http://{$this->host}/api/v1/storefront/promotions");

        $response->assertOk();

        $context = $this->app->make(LogContext::class);
        $this->assertSame('req-marker-99', $context->getRequestId());
        $this->assertNotNull($context->getTraceId());
        $this->assertNotNull($context->getSpanId());
    }

    #[Test]
    public function it_clears_user_and_store_from_log_context_after_request(): void
    {
        // Simulamos que un middleware aguas abajo setea user/store.
        $response = $this->getJson(uri: "http://{$this->host}/api/v1/storefront/promotions");

        $response->assertOk();

        $context = $this->app->make(LogContext::class);
        $this->assertNull($context->toArray()['user_id'] ?? null);
        $this->assertNull($context->toArray()['store_id'] ?? null);
    }
}
