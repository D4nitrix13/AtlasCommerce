<?php

declare(strict_types=1);

namespace App\Logging;

use Ramsey\Uuid\Uuid;

/**
 * Holder singleton del contexto de correlación para la request en curso.
 *
 * Lo completa `App\Http\Middleware\CorrelationIdMiddleware` al inicio
 * de cada request y lo consume `App\Logging\ContextProcessor` para
 * enriquecer cada log con los identificadores de trazabilidad.
 *
 * Diseño:
 *  - Singleton por request (registrado como singleton en
 *    `AppServiceProvider`): todas las capas ven la misma instancia.
 *  - Tres identificadores independientes con propósitos distintos:
 *      • `request_id` — identificador de la HTTP request. UUIDv7 (time-
 *        orderable). Lo emite el cliente (`X-Request-Id`) o lo generamos.
 *      • `trace_id` — identificador del trace distribuido. 32 hex
 *        según W3C tracecontext spec. Se propaga vía header `traceparent`
 *        si el upstream lo emite; si no, se genera uno nuevo.
 *      • `span_id` — identificador del span local. 16 hex. SIEMPRE
 *        nuevo por request (representa el segmento de trabajo en este
 *        servicio); `parent_span_id` lleva el span del caller para
 *        reconstruir el árbol de spans.
 *  - Flags de muestreo: si el trace entrante viene con `sampled=0`,
 *    respetamos la decisión upstream y dejamos `sampled=false`.
 *
 * Importante: este contexto es por-request, no compartido entre jobs
 * de cola. En jobs, una vez enqueueados, se les pasa `trace_id` y
 * `request_id` como parte del payload (futuro enhancement — ver
 * PR3 cuando se conecte con OpenTelemetry).
 */
class LogContext
{
    private ?string $requestId = null;

    private ?string $traceId = null;

    private ?string $spanId = null;

    private ?string $parentSpanId = null;

    private bool $sampled = true;

    private ?int $userId = null;

    private ?int $storeId = null;

    /**
     * Inicializa el contexto de correlación para la request en curso.
     *
     * Si viene un `traceparent` válido, conserva el `trace_id` y usa el
     * `parent_id` del upstream como `parent_span_id`. Si no, genera un
     * trace nuevo.
     *
     * @param  string|null  $incomingTraceparent  Header `traceparent` recibido (W3C).
     * @param  string|null  $incomingRequestId  Header `X-Request-Id` recibido.
     */
    public function start(?string $incomingTraceparent, ?string $incomingRequestId): void
    {
        $this->requestId = self::sanitizeRequestId($incomingRequestId)
            ?? Uuid::uuid7()->toString();

        $parsed = self::parseTraceparent($incomingTraceparent);
        if ($parsed !== null) {
            $this->traceId = $parsed['trace_id'];
            $this->parentSpanId = $parsed['parent_id'];
            $this->sampled = $parsed['sampled'];
        } else {
            $this->traceId = bin2hex(random_bytes(16));
            $this->parentSpanId = null;
            $this->sampled = true;
        }

        // SIEMPRE generamos un span_id nuevo — representa el segmento
        // de trabajo que ocurre en ESTE servicio.
        $this->spanId = bin2hex(random_bytes(8));
    }

    /**
     * Serializa el contexto al formato W3C `traceparent` para propagarlo
     * al siguiente hop (downstream HTTP calls).
     *
     * Formato: `00-<trace_id 32 hex>-<span_id 16 hex>-<flags 2 hex>`.
     */
    public function buildTraceparent(): string
    {
        if ($this->traceId === null || $this->spanId === null) {
            // Estado inválido — no se llamó a start(). Devolvemos un
            // placeholder en lugar de explotar silenciosamente.
            return '00-00000000000000000000000000000000-0000000000000000-00';
        }

        return sprintf(
            '00-%s-%s-%02x',
            $this->traceId,
            $this->spanId,
            $this->sampled ? 0x01 : 0x00,
        );
    }

    /**
     * Devuelve los campos del contexto como array asociativo, omitiendo
     * los valores nulos. Lo usa `ContextProcessor` para enriquecer cada
     * log record.
     *
     * @return array<string, string|int|bool>
     */
    public function toArray(): array
    {
        return array_filter(
            array: [
                'request_id' => $this->requestId,
                'trace_id' => $this->traceId,
                'span_id' => $this->spanId,
                'parent_span_id' => $this->parentSpanId,
                'user_id' => $this->userId,
                'store_id' => $this->storeId,
                'sampled' => $this->sampled,
            ],
            callback: static fn (mixed $value): bool => $value !== null,
        );
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function getTraceId(): ?string
    {
        return $this->traceId;
    }

    public function getSpanId(): ?string
    {
        return $this->spanId;
    }

    public function isSampled(): bool
    {
        return $this->sampled;
    }

    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
    }

    public function setStoreId(?int $storeId): void
    {
        $this->storeId = $storeId;
    }

    /**
     * Parsea un header `traceparent` W3C.
     *
     * Formato esperado: `00-<trace_id>-<parent_id>-<flags>`.
     * Valida largo y contenido hex; si algo no encaja, retorna null
     * (no rompe la request — el caller genera un trace nuevo).
     *
     * @return array{trace_id: string, parent_id: string, sampled: bool}|null
     */
    private static function parseTraceparent(?string $header): ?array
    {
        if ($header === null) {
            return null;
        }

        // 55 chars: 2 (version) + 1 (-) + 32 (trace) + 1 (-) + 16 (parent) + 1 (-) + 2 (flags)
        if (strlen($header) !== 55) {
            return null;
        }

        if (preg_match('/^00-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})$/', $header, $matches) !== 1) {
            return null;
        }

        // trace_id y parent_id no pueden ser todos cero (regla W3C).
        if ($matches[1] === str_repeat('0', 32) || $matches[2] === str_repeat('0', 16)) {
            return null;
        }

        return [
            'trace_id' => $matches[1],
            'parent_id' => $matches[2],
            'sampled' => (hexdec($matches[3]) & 0x01) === 0x01,
        ];
    }

    /**
     * Sanitiza un request_id entrante. Acepta solo chars seguros para
     * evitar log injection y header smuggling.
     */
    private static function sanitizeRequestId(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        // Max 128 chars, solo [A-Za-z0-9._-] (UUID-friendly + trace-id friendly).
        if ($value === '' || strlen($value) > 128 || preg_match('/^[A-Za-z0-9._-]+$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
