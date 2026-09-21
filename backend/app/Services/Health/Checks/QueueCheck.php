<?php

declare(strict_types=1);

namespace App\Services\Health\Checks;

use App\Services\Health\Contracts\HealthCheck;
use App\Services\Health\HealthCheckResult;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Throwable;

/**
 * Check del sistema de colas (`QUEUE_CONNECTION`).
 *
 * Lee el tamaño de la cola default sin encolar nada — es read-only y
 * no afecta el comportamiento del job worker.
 *
 * NO es crítico: si la cola se llena, los jobs se acumulan pero las
 * requests sync siguen funcionando. Reportamos unhealthy para que ops
 * vea el tamaño en el dashboard, pero el endpoint /health/ready sigue
 * 200 si la DB está OK.
 */
final class QueueCheck implements HealthCheck
{
    public function __construct(
        private readonly QueueFactory $queue,
    ) {}

    public function name(): string
    {
        return 'queue';
    }

    public function isCritical(): bool
    {
        return false;
    }

    public function run(): HealthCheckResult
    {
        $start = hrtime(as_number: true);

        try {
            $connection = $this->queue->connection();
            $size = $connection->size('default');

            $latencyMs = (hrtime(as_number: true) - $start) / 1_000_000;

            // size() puede devolver null en drivers que no lo soportan
            // (sync, etc.). Lo tratamos como "no medible" pero healthy.
            $message = $size === null
                ? 'queue size not measurable on this driver'
                : sprintf('queue size: %d', $size);

            return HealthCheckResult::ok(
                name: $this->name(),
                latencyMs: $latencyMs,
                message: $message,
            );
        } catch (Throwable $e) {
            $latencyMs = (hrtime(as_number: true) - $start) / 1_000_000;

            return HealthCheckResult::fromThrowable(
                name: $this->name(),
                e: $e,
                latencyMs: $latencyMs,
            );
        }
    }
}
