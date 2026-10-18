<?php

declare(strict_types=1);

namespace App\Tracing;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ScopeInterface;
use Throwable;

/**
 * Wrapper sobre un span de OpenTelemetry con API ergonómica para los
 * call sites del dominio. Maneja el ciclo de vida del scope y
 * garantiza que el span se cierre al hacer `end()`.
 */
final class TracerSpan
{
    private bool $ended = false;

    public function __construct(
        private readonly SpanInterface $span,
        private readonly ScopeInterface $scope,
    ) {}

    /**
     * Set a single attribute on the span. Idempotent (si la key ya existe,
     * reemplaza). Fluent — retorna `$this` para chaining.
     *
     * @param  string  $key  Nombre del attribute (kebab-case por convención OTel)
     * @param  mixed  $value  Valor serializable. NO pasar closures ni objects grandes.
     */
    public function setAttribute(string $key, mixed $value): self
    {
        $this->span->setAttribute($key, $value);

        return $this;
    }

    /**
     * Set multiple attributes de una vez. Equivalente a iterar
     * `setAttribute()` para cada item, pero atómico a nivel del SDK.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function setAttributes(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->span->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Registra una exception en el span via OTel event `exception`.
     *
     * **Importante**: NO termina el span, NO marca como error automáticamente.
     * Si querés marcar el span como failed, llamar después a `setStatusError()`.
     * El span se cerrará recién cuando hagas `end()` (o cuando el objeto
     * se destruya via destructor).
     *
     * @param  Throwable  $e  Exception que querés registrar
     * @param  array<string, mixed>  $attributes  Attributes extra para el event (ej: 'error.kind')
     */
    public function recordException(Throwable $e, array $attributes = []): self
    {
        $this->span->recordException($e, $attributes);

        return $this;
    }

    /**
     * Marca el span como `Error` con una descripción humana.
     *
     * Llamar típicamente después de `recordException()` para que el span
     * completo (no solo el event de exception) quede como failed en el
     * backend de tracing.
     *
     * @param  string  $description  Mensaje corto (1-2 frases)
     */
    public function setStatusError(string $description): self
    {
        $this->span->setStatus(
            \OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR,
            $description,
        );

        return $this;
    }

    /**
     * Cierra el span y libera el scope activo.
     *
     * **Idempotente**: llamadas subsecuentes son no-op (chequeado via
     * flag `$ended`). El destructor también llama a `end()` —放心
     * que si lo olvidás, se cierra al salir del scope.
     *
     * Llamar manualmente cuando sabés que el span debe terminar (ej: fin
     * del método HTTP). Confiar en el destructor solo en casos donde
     * el objeto vive poco (ej: dentro de un try/finally corto).
     */
    public function end(): void
    {
        if ($this->ended) {
            return;
        }

        $this->scope->detach();
        $this->span->end();
        $this->ended = true;
    }

    /**
     * Cierra el span automáticamente al destruir el objeto.
     * Llamar a `end()` manualmente sigue siendo preferible en hot paths
     * porque libera el scope sooner (mejor propagación de trace context).
     */
    public function __destruct()
    {
        $this->end();
    }

    /**
     * Acceso al span crudo de OTel. Usar solo si necesitás algo fuera
     * de la API ergonómica de esta clase (ej: events custom, links a
     * otros spans). Para setAttribute/setStatus preferí los métodos
     * de TracerSpan (mantiene el contract idempotente).
     */
    public function span(): SpanInterface
    {
        return $this->span;
    }
}
