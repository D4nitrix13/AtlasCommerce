<?php

declare(strict_types=1);

namespace App\Services\Health\Checks;

use App\Services\Health\Contracts\HealthCheck;
use App\Services\Health\HealthCheckResult;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Check de conectividad a la base de datos principal.
 *
 * Ejecuta `SELECT 1` con un timeout corto para no colgar `/health/ready`
 * si la DB está caída. CRÍTICO — sin DB no hay nada que servir.
 */
final class DatabaseCheck implements HealthCheck
{
    public function __construct(
        private readonly int $timeoutSeconds = 2,
    ) {}

    public function name(): string
    {
        return 'database';
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        $start = hrtime(as_number: true);

        try {
            $result = DB::connection()->selectOne('SELECT 1 AS ok');

            $latencyMs = (hrtime(as_number: true) - $start) / 1_000_000;

            if (! isset($result->ok) || (int) $result->ok !== 1) {
                return HealthCheckResult::fail(
                    name: $this->name(),
                    message: 'SELECT 1 returned unexpected result.',
                    latencyMs: $latencyMs,
                );
            }

            return HealthCheckResult::ok(name: $this->name(), latencyMs: $latencyMs);
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
