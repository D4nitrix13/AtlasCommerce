<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\LogContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests de `App\Logging\LogContext` (RF-OBS-130 — Fase 13).
 *
 * Cubre:
 *  - Generación de request_id cuando no viene del cliente.
 *  - Conservación del request_id entrante cuando es válido.
 *  - Sanitización del request_id (rechaza chars peligrosos, longitud
 *    excesiva, vacíos).
 *  - Parsing del header `traceparent` W3C: válido, malformado, all-zero.
 *  - Generación de trace_id y span_id nuevos cuando no hay traceparent.
 *  - `buildTraceparent()` roundtrip: trace_id y span_id se preservan,
 *    flags de muestreo se respetan.
 *  - `toArray()` omite nulos.
 */
#[Group('observability')]
#[Group('logging')]
final class LogContextTest extends TestCase
{
    #[Test]
    public function it_generates_request_id_when_absent(): void
    {
        $context = new LogContext;
        $context->start(incomingTraceparent: null, incomingRequestId: null);

        $requestId = $context->getRequestId();
        $this->assertNotNull($requestId);
        // UUIDv7 → empieza con timestamp + version 7.
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $requestId,
        );
    }

    #[Test]
    public function it_consumes_incoming_request_id_when_valid(): void
    {
        $context = new LogContext;
        $context->start(incomingTraceparent: null, incomingRequestId: 'client-abc-123');

        $this->assertSame('client-abc-123', $context->getRequestId());
    }

    #[Test]
    #[DataProvider('invalidRequestIds')]
    public function it_rejects_invalid_incoming_request_ids(string $invalid): void
    {
        $context = new LogContext;
        $context->start(incomingTraceparent: null, incomingRequestId: $invalid);

        // Invalido → fallback a UUIDv7 generado.
        $this->assertNotSame($invalid, $context->getRequestId());
        $this->assertNotNull($context->getRequestId());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidRequestIds(): array
    {
        return [
            'con newlines (header smuggling)' => ["abc\ndef"],
            'con espacios' => ['abc def'],
            'con punto y coma (log injection)' => ['abc;evil'],
            'vacio' => [''],
            'demasiado largo' => [str_repeat('a', 129)],
        ];
    }

    #[Test]
    public function it_propagates_valid_incoming_traceparent(): void
    {
        $incoming = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

        $context = new LogContext;
        $context->start(incomingTraceparent: $incoming, incomingRequestId: null);

        // El trace_id del upstream se conserva.
        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $context->getTraceId());
        // El span_id de ESTE servicio SIEMPRE es nuevo (representa el
        // segmento de trabajo local), distinto al parent_id upstream.
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $context->getSpanId());
        $this->assertNotSame('00f067aa0ba902b7', $context->getSpanId());
        $this->assertTrue($context->isSampled());

        // El parent_span_id queda registrado (aunque no esté expuesto por
        // getter público, sí aparece en toArray()).
        $array = $context->toArray();
        $this->assertSame('00f067aa0ba902b7', $array['parent_span_id']);
    }

    #[Test]
    public function it_respects_unsampled_flag_from_upstream(): void
    {
        // sampled = 00 → no muestrear.
        $incoming = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-00';

        $context = new LogContext;
        $context->start(incomingTraceparent: $incoming, incomingRequestId: null);

        $this->assertFalse($context->isSampled());
    }

    #[Test]
    #[DataProvider('malformedTraceparents')]
    public function it_falls_back_to_new_trace_when_traceparent_is_malformed(string $malformed): void
    {
        $context = new LogContext;
        $context->start(incomingTraceparent: $malformed, incomingRequestId: null);

        // trace_id generado nuevo (32 hex no-cero).
        $this->assertNotNull($context->getTraceId());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $context->getTraceId());
        $this->assertNotSame(str_repeat('0', 32), $context->getTraceId());
        $this->assertTrue($context->isSampled()); // default
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedTraceparents(): array
    {
        return [
            'largo incorrecto' => ['00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7'],
            'version no soportada' => ['ff-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'],
            'chars no-hex' => ['00-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-00f067aa0ba902b7-01'],
            'all-zero trace_id' => ['00-00000000000000000000000000000000-00f067aa0ba902b7-01'],
            'all-zero parent_id' => ['00-4bf92f3577b34da6a3ce929d0e0e4736-0000000000000000-01'],
        ];
    }

    #[Test]
    public function it_generates_unique_span_ids_per_request(): void
    {
        $context1 = new LogContext;
        $context1->start(incomingTraceparent: null, incomingRequestId: null);

        $context2 = new LogContext;
        $context2->start(incomingTraceparent: null, incomingRequestId: null);

        $this->assertNotSame($context1->getSpanId(), $context2->getSpanId());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $context1->getSpanId());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $context2->getSpanId());
    }

    #[Test]
    public function build_traceparent_roundtrips_trace_id_and_span_id(): void
    {
        $context = new LogContext;
        $context->start(incomingTraceparent: null, incomingRequestId: null);

        $traceparent = $context->buildTraceparent();

        $this->assertMatchesRegularExpression(
            '/^00-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}$/',
            $traceparent,
        );

        // Debe matchear los IDs que generamos.
        $parts = explode('-', $traceparent);
        $this->assertSame($context->getTraceId(), $parts[1]);
        $this->assertSame($context->getSpanId(), $parts[2]);
    }

    #[Test]
    public function it_omits_null_fields_in_to_array(): void
    {
        $context = new LogContext;
        $context->setUserId(null);
        $context->setStoreId(null);

        $context->start(incomingTraceparent: null, incomingRequestId: null);

        $array = $context->toArray();

        $this->assertArrayHasKey('request_id', $array);
        $this->assertArrayHasKey('trace_id', $array);
        $this->assertArrayHasKey('span_id', $array);
        $this->assertArrayNotHasKey('user_id', $array);
        $this->assertArrayNotHasKey('store_id', $array);
        $this->assertArrayNotHasKey('parent_span_id', $array); // era null
        $this->assertTrue($array['sampled']);
    }

    #[Test]
    public function it_includes_user_and_store_ids_when_set(): void
    {
        $context = new LogContext;
        $context->start(incomingTraceparent: null, incomingRequestId: null);
        $context->setUserId(42);
        $context->setStoreId(7);

        $array = $context->toArray();

        $this->assertSame(42, $array['user_id']);
        $this->assertSame(7, $array['store_id']);
    }

    #[Test]
    public function it_can_clear_user_and_store_after_request(): void
    {
        $context = new LogContext;
        $context->start(incomingTraceparent: null, incomingRequestId: null);
        $context->setUserId(42);
        $context->setStoreId(7);

        $context->setUserId(null);
        $context->setStoreId(null);

        $array = $context->toArray();
        $this->assertArrayNotHasKey('user_id', $array);
        $this->assertArrayNotHasKey('store_id', $array);
    }
}
