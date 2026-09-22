<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use App\Logging\LogContext;
use App\Observability\SentryBreadcrumbsConfigurator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sentry\Breadcrumb;
use Tests\TestCase;

/**
 * Unit tests de `SentryBreadcrumbsConfigurator` (RF-OBS-134).
 *
 * Verifica:
 *  - Breadcrumb SIN contexto de correlación: pasa sin modificar.
 *  - Breadcrumb CON contexto: recibe request_id, trace_id, span_id
 *    en metadata.
 */
#[Group('observability')]
#[Group('sentry')]
final class SentryBreadcrumbsConfiguratorTest extends TestCase
{
    #[Test]
    public function breadcrumb_without_correlation_context_passes_through(): void
    {
        $configurator = new SentryBreadcrumbsConfigurator(new LogContext);
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'test.event', 'message');

        $result = $configurator($breadcrumb);

        $this->assertSame($breadcrumb, $result);
        $this->assertSame([], $result->getMetadata());
    }

    #[Test]
    public function breadcrumb_with_correlation_context_receives_ids(): void
    {
        $logContext = new LogContext;
        $logContext->start(incomingTraceparent: null, incomingRequestId: 'req-123');

        $configurator = new SentryBreadcrumbsConfigurator($logContext);
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'test.event', 'message');

        $result = $configurator($breadcrumb);

        $metadata = $result->getMetadata();
        $this->assertSame('req-123', $metadata['request_id']);
        $this->assertSame($logContext->getTraceId(), $metadata['trace_id']);
        $this->assertSame($logContext->getSpanId(), $metadata['span_id']);
    }

    #[Test]
    public function breadcrumb_preserves_existing_metadata(): void
    {
        $logContext = new LogContext;
        $logContext->start(incomingTraceparent: null, incomingRequestId: 'req-456');

        $configurator = new SentryBreadcrumbsConfigurator($logContext);
        $breadcrumb = (new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'test.event', 'message'))
            ->withMetadata('category', 'auth')
            ->withMetadata('attempt', 1);

        $result = $configurator($breadcrumb);

        $metadata = $result->getMetadata();
        $this->assertSame('auth', $metadata['category']);
        $this->assertSame(1, $metadata['attempt']);
        $this->assertSame('req-456', $metadata['request_id']);
    }
}
