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

    public function setAttribute(string $key, mixed $value): self
    {
        $this->span->setAttribute($key, $value);

        return $this;
    }

    public function setAttributes(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->span->setAttribute($key, $value);
        }

        return $this;
    }

    public function recordException(Throwable $e, array $attributes = []): self
    {
        $this->span->recordException($e, $attributes);

        return $this;
    }

    public function setStatusError(string $description): self
    {
        $this->span->setStatus(
            \OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR,
            $description,
        );

        return $this;
    }

    public function end(): void
    {
        if ($this->ended) {
            return;
        }

        $this->scope->detach();
        $this->span->end();
        $this->ended = true;
    }

    public function __destruct()
    {
        $this->end();
    }

    public function span(): SpanInterface
    {
        return $this->span;
    }
}
