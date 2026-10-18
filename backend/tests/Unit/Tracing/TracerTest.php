<?php

declare(strict_types=1);

namespace Tests\Unit\Tracing;

use App\Logging\LogContext;
use App\Tracing\Tracer;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests del `Tracer` (RF-OBS-133 — Fase 13 PR4).
 *
 * Cubre:
 *  - Modo noop (default en dev): start() retorna un TracerSpan que
 *    no hace nada. No hay overhead, no se exportan spans.
 *  - Modo memory: start() exporta el span al InMemoryExporter,
 *    verificable via getSpans().
 *  - inSpan(): wrap automático con recordException + end.
 *  - TracerSpan: idempotencia de end(), propagación de atributos.
 */
#[Group('observability')]
#[Group('tracing')]
final class TracerTest extends TestCase
{
    #[Test]
    public function noop_tracer_creates_inert_span(): void
    {
        $tracer = $this->makeTracer(enabled: false);

        $span = $tracer->start(name: 'test.noop', attributes: ['x' => 1]);

        $this->assertNotNull($span);

        // No debe lanzar nada — end() debe ser idempotente.
        $span->end();
        $span->end(); // segunda llamada no rompe.
    }

    #[Test]
    public function memory_tracer_exports_span_with_attributes(): void
    {
        $exporter = new InMemoryExporter;
        $tracer = $this->makeTracer(enabled: true, exporter: $exporter);

        $tracer->start(name: 'test.export', attributes: ['http.method' => 'GET'])
            ->setAttribute('http.status_code', 200)
            ->end();

        $spans = $exporter->getSpans();
        $this->assertCount(1, $spans);

        $span = $spans[0];
        $this->assertSame('test.export', $span->getName());
        $this->assertSame(200, $span->getAttributes()->get('http.status_code'));
        $this->assertSame('GET', $span->getAttributes()->get('http.method'));
    }

    #[Test]
    public function in_span_records_exception_and_rethrows(): void
    {
        $exporter = new InMemoryExporter;
        $tracer = $this->makeTracer(enabled: true, exporter: $exporter);

        $thrown = false;
        try {
            $tracer->inSpan('test.error', function (): void {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException $e) {
            $thrown = true;
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertTrue($thrown);
        $this->assertCount(1, $exporter->getSpans());
    }

    #[Test]
    public function in_span_returns_value_when_no_exception(): void
    {
        $tracer = $this->makeTracer(enabled: false);

        $result = $tracer->inSpan('test.value', fn () => 42);

        $this->assertSame(42, $result);
    }

    #[Test]
    public function tracer_provider_is_exposed(): void
    {
        $tracer = $this->makeTracer(enabled: false);

        $this->assertInstanceOf(NoopTracerProvider::class, $tracer->provider());
    }

    private function makeTracer(bool $enabled, ?InMemoryExporter $exporter = null): Tracer
    {
        $config = [
            'enabled' => $enabled,
            'exporter' => $exporter !== null ? 'memory' : 'noop',
            'service_name' => 'test',
            'service_version' => '0.0.0',
            'otlp' => ['endpoint' => 'http://localhost:4317'],
        ];

        $tracer = new Tracer(
            logContext: new LogContext,
            config: $config,
        );

        // Si nos dieron un exporter memory, reemplazamos el provider.
        if ($exporter !== null && $enabled) {
            $reflection = new ReflectionClass($tracer);
            $providerProp = $reflection->getProperty('provider');
            $providerProp->setAccessible(true);
            $providerProp->setValue($tracer, TracerProvider::builder()
                ->addSpanProcessor(new SimpleSpanProcessor($exporter))
                ->build());

            $tracerProp = $reflection->getProperty('tracer');
            $tracerProp->setAccessible(true);
            $tracerProp->setValue($tracer, $providerProp->getValue($tracer)->getTracer('test'));
        }

        return $tracer;
    }
}
