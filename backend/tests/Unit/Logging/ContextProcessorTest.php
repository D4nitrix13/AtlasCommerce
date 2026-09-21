<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\ContextProcessor;
use App\Logging\LogContext;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests de `App\Logging\ContextProcessor` (RF-OBS-130 — Fase 13).
 *
 * Verifica que cada log record emitido durante una request queda
 * enriquecido con los IDs de correlación del `LogContext` activo.
 */
#[Group('observability')]
#[Group('logging')]
final class ContextProcessorTest extends TestCase
{
    #[Test]
    public function it_enriches_log_record_with_correlation_context(): void
    {
        $context = new LogContext;
        $context->start(incomingTraceparent: null, incomingRequestId: 'req-abc-123');
        $context->setUserId(99);
        $context->setStoreId(3);

        $processor = new ContextProcessor($context);

        $record = new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'production',
            level: Level::Info,
            message: 'order created',
            context: ['order_id' => 555],
            extra: [],
        );

        $enriched = $processor($record);

        $this->assertSame('req-abc-123', $enriched->extra['request_id']);
        $this->assertSame($context->getTraceId(), $enriched->extra['trace_id']);
        $this->assertSame($context->getSpanId(), $enriched->extra['span_id']);
        $this->assertSame(99, $enriched->extra['user_id']);
        $this->assertSame(3, $enriched->extra['store_id']);
    }

    #[Test]
    public function it_preserves_existing_extra_fields(): void
    {
        $context = new LogContext;
        $context->start(incomingTraceparent: null, incomingRequestId: null);

        $processor = new ContextProcessor($context);

        $record = new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'production',
            level: Level::Info,
            message: 'cached lookup',
            context: [],
            extra: ['cache_key' => 'order:42', 'hit' => true],
        );

        $enriched = $processor($record);

        $this->assertSame('order:42', $enriched->extra['cache_key']);
        $this->assertTrue($enriched->extra['hit']);
        $this->assertArrayHasKey('trace_id', $enriched->extra);
    }

    #[Test]
    public function it_minimal_extra_when_context_is_empty(): void
    {
        // LogContext recién creado sin start() → solo `sampled` queda
        // (defaults a true, no es nullable). El resto (request_id,
        // trace_id, span_id) son null y se filtran.
        $context = new LogContext;
        $processor = new ContextProcessor($context);

        $record = new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'production',
            level: Level::Info,
            message: 'startup',
            context: [],
            extra: [],
        );

        $enriched = $processor($record);

        // Solo `sampled` aparece; los IDs no porque son null.
        $this->assertSame(['sampled' => true], $enriched->extra);
    }
}
