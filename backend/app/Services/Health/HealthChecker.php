<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Services\Health\Contracts\HealthCheck;
use Illuminate\Support\Collection;

/**
 * Orquesta la ejecución de todos los `HealthCheck` registrados y
 * agrega el resultado en una estructura apta para serializar a JSON.
 *
 * Separa checks CRÍTICOS de informativos:
 *  - Críticos: su fallo marca el status global como `fail` → la response
 *    de `/health/ready` es 503 → el orquestador retira la instancia.
 *  - Informativos: aparecen en el payload pero no afectan el status
 *    code (ej: queue size, útil para dashboards sin quitar tráfico).
 *
 * Los checks se ejecutan en serie — son baratos (< 50ms cada uno) y en
 * serie se evita ocultar fallos detrás de timeouts paralelos.
 *
 * Acepta `HealthCheck` por contrato (no concretas) para:
 *  - Testear con doubles anónimos sin depender de implementaciones.
 *  - Agregar/quitar checks sin tocar el orquestador.
 */
final class HealthChecker
{
    /**
     * @var Collection<string, HealthCheck>
     */
    private readonly Collection $checks;

    public function __construct(
        HealthCheck $database,
        HealthCheck $storage,
        HealthCheck $queue,
    ) {
        $this->checks = collect([
            $database,
            $storage,
            $queue,
        ])->keyBy(keyBy: static fn (HealthCheck $check): string => $check->name());
    }

    /**
     * Ejecuta todos los checks y devuelve un array con el agregado.
     *
     * Estructura del payload:
     * ```php
     * [
     *     'healthy' => bool,         // true solo si TODOS los críticos pasan
     *     'status' => 'ok'|'fail',
     *     'timestamp' => '2026-09-21T...',
     *     'checks' => [              // resultado individual por check
     *         'database' => ['name' => ..., 'healthy' => ..., 'latency_ms' => ...],
     *         'storage' => [...],
     *         'queue' => [...],
     *     ],
     * ]
     * ```
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $results = $this->checks
            ->map(callback: static fn (HealthCheck $check): HealthCheckResult => $check->run());

        $criticalFailed = $this->checks
            ->filter(callback: static fn (HealthCheck $check): bool => $check->isCritical())
            ->contains(key: function (HealthCheck $check) use ($results): bool {
                $result = $results->get(key: $check->name());

                return $result instanceof HealthCheckResult && ! $result->healthy;
            });

        $healthy = ! $criticalFailed;

        return [
            'healthy' => $healthy,
            'status' => $healthy ? 'ok' : 'fail',
            'timestamp' => now()->toIso8601String(),
            'checks' => $results
                ->map(callback: static fn (HealthCheckResult $r): array => $r->toArray())
                ->all(),
        ];
    }

    /**
     * Helper para que el controller pueda mapear `healthy` → status HTTP
     * sin repetir lógica en cada endpoint.
     */
    public function isReady(): bool
    {
        return $this->run()['healthy'] === true;
    }
}
