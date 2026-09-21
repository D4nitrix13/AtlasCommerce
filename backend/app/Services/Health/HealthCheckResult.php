<?php

declare(strict_types=1);

namespace App\Services\Health;

use Throwable;

/**
 * DTO inmutable con el resultado de un check individual.
 *
 * Se serializa a JSON en la response de `/health` para que ops pueda
 * ver exactamente qué falla sin tener que abrir logs.
 */
final readonly class HealthCheckResult
{
    public function __construct(
        public string $name,
        public bool $healthy,
        public ?string $message = null,
        public float $latencyMs = 0.0,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: (string) ($payload['name'] ?? 'unknown'),
            healthy: (bool) ($payload['healthy'] ?? false),
            message: isset($payload['message']) ? (string) $payload['message'] : null,
            latencyMs: (float) ($payload['latency_ms'] ?? 0.0),
        );
    }

    public static function ok(string $name, float $latencyMs = 0.0, ?string $message = null): self
    {
        return new self(name: $name, healthy: true, message: $message, latencyMs: $latencyMs);
    }

    public static function fail(string $name, string $message, float $latencyMs = 0.0): self
    {
        return new self(name: $name, healthy: false, message: $message, latencyMs: $latencyMs);
    }

    public static function fromThrowable(string $name, Throwable $e, float $latencyMs = 0.0): self
    {
        // No exponemos el stack trace en la response pública — solo la
        // clase de la excepción y el mensaje. Stack va a logs (que ya
        // tienen correlation IDs vía Fase 13 PR1).
        $message = sprintf('%s: %s', $e::class, $e->getMessage());

        return self::fail(name: $name, message: $message, latencyMs: $latencyMs);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            array: [
                'name' => $this->name,
                'healthy' => $this->healthy,
                'message' => $this->message,
                'latency_ms' => round(num: $this->latencyMs, precision: 2),
            ],
            callback: static fn (mixed $value, string $key): bool => $key !== 'message' || $value !== null,
            mode: ARRAY_FILTER_USE_BOTH,
        );
    }
}
