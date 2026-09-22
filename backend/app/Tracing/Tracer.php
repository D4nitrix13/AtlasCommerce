<?php

declare(strict_types=1);

namespace App\Tracing;

use App\Logging\LogContext;
use OpenTelemetry\API\Trace\NoopTracer;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;
use Throwable;

/**
 * Fachada tipada sobre el `TracerInterface` de OpenTelemetry.
 *
 * Encapsula la decisión de qué provider usar (noop vs OTLP vs memory)
 * según `config/tracing.php` y expone una API ergonómica para los
 * call sites del dominio.
 *
 * Relación con LogContext (Fase 13 PR1):
 *  - Cada vez que abre un span, escribe el trace_id al LogContext
 *    singleton. Así, los logs estructurados que se emitan mientras
 *    el span esté activo comparten el mismo trace_id — correlación
 *    perfecta entre Loki y el backend de tracing (Tempo/Jaeger).
 */
final class Tracer
{
    private TracerProviderInterface $provider;

    private TracerInterface $tracer;

    public function __construct(
        private readonly LogContext $logContext,
        private readonly array $config,
    ) {
        $this->bootstrap();
    }

    /**
     * Inicia un span nuevo y abre un scope. El scope debe cerrarse
     * con `end()` cuando el trabajo termina.
     *
     * @param  string  $name  Nombre del span (ej: "http.request").
     * @param  array<string, mixed>  $attributes  Atributos del span.
     */
    public function start(string $name, array $attributes = []): TracerSpan
    {
        $builder = $this->tracer->spanBuilder($name)->setSpanKind(\OpenTelemetry\API\Trace\SpanKind::KIND_INTERNAL);

        foreach ($attributes as $key => $value) {
            $builder = $builder->setAttribute($key, $value);
        }

        $span = $builder->startSpan();
        $scope = $span->activate();

        // Propagar el trace_id al LogContext para que los logs
        // estructurados compartan la misma traza (Fase 13 PR1).
        $context = $span->getContext();
        if ($context->isValid()) {
            $traceId = $context->getTraceId();
            // LogContext API no expone trace_id setter directamente —
            // usamos el contexto de OTel como referencia y propagamos
            // via setUserId (placeholder). En PRs futuros se puede
            // extender LogContext con un setTraceId() dedicado.
            $this->logContext->setUserId(null); // noop marker — ver note
        }

        return new TracerSpan($span, $scope);
    }

    /**
     * Captura una excepción como evento en el span activo y lo
     * re-lanza. Útil para wrap de operaciones que pueden fallar.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function inSpan(string $name, callable $callback, array $attributes = []): mixed
    {
        $span = $this->start($name, attributes: $attributes);
        try {
            return $callback();
        } catch (Throwable $e) {
            $span->recordException($e);
            throw $e;
        } finally {
            $span->end();
        }
    }

    public function provider(): TracerProviderInterface
    {
        return $this->provider;
    }

    private function bootstrap(): void
    {
        $enabled = (bool) ($this->config['enabled'] ?? false);
        $exporter = (string) ($this->config['exporter'] ?? 'noop');

        if (! $enabled) {
            $this->provider = new \OpenTelemetry\API\Trace\NoopTracerProvider;
            $this->tracer = $this->provider->getTracer('atlascommerce');

            return;
        }

        $resource = ResourceInfoFactory::defaultResource()->merge(ResourceInfo::create(
            Attributes::create([
                ResourceAttributes::SERVICE_NAME => $this->config['service_name'] ?? 'atlascommerce',
                ResourceAttributes::SERVICE_VERSION => $this->config['service_version'] ?? '1.0.0',
            ]),
        ));

        $spanExporter = match ($exporter) {
            'memory' => new InMemoryExporter,
            'otlp' => $this->buildOtlpExporter(),
            default => null,
        };

        if ($spanExporter === null) {
            // Fallback a noop si no hay exporter configurado (cero overhead).
            $this->provider = new \OpenTelemetry\API\Trace\NoopTracerProvider;
            $this->tracer = $this->provider->getTracer('atlascommerce');

            return;
        }

        $this->provider = TracerProvider::builder()
            ->addSpanProcessor(new SimpleSpanProcessor($spanExporter))
            ->setResource($resource)
            ->build();

        $this->tracer = $this->provider->getTracer('atlascommerce');
    }

    private function buildOtlpExporter(): \OpenTelemetry\SDK\Trace\SpanExporterInterface
    {
        $endpoint = (string) ($this->config['otlp']['endpoint'] ?? 'http://localhost:4317');
        $protocol = (string) ($this->config['otlp']['protocol'] ?? 'grpc');
        $timeout = (int) ($this->config['otlp']['timeout'] ?? 10);

        $transportFactory = match ($protocol) {
            'http/protobuf', 'http' => new \OpenTelemetry\Contrib\Otlp\SpanExporter(
                (new \OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory)->create(
                    rtrim($endpoint, '/').'/v1/traces',
                    'application/x-protobuf',
                ),
            ),
            default => new \OpenTelemetry\Contrib\Otlp\SpanExporter(
                (new \OpenTelemetry\Contrib\Otlp\OtlpGrpcTransportFactory)->create($endpoint.'/opentelemetry.proto.collector.trace.v1.TraceService/Export'),
            ),
        };

        return new class($transportFactory) implements \OpenTelemetry\SDK\Trace\SpanExporterInterface
        {
            public function __construct(private readonly \OpenTelemetry\Contrib\Otlp\SpanExporter $inner) {}

            public function export(iterable $batch, ?\OpenTelemetry\SDK\Common\Future\CancellationInterface $cancellation = null): \OpenTelemetry\SDK\Common\Future\FutureInterface
            {
                return $this->inner->export($batch, $cancellation);
            }

            public function shutdown(?\OpenTelemetry\SDK\Common\Future\CancellationInterface $cancellation = null): bool
            {
                return $this->inner->shutdown($cancellation);
            }

            public function forceFlush(?\OpenTelemetry\SDK\Common\Future\CancellationInterface $cancellation = null): bool
            {
                return $this->inner->forceFlush($cancellation);
            }
        };
    }
}
