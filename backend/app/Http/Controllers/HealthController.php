<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Health\Checks\DatabaseCheck;
use App\Services\Health\Checks\QueueCheck;
use App\Services\Health\Checks\StorageCheck;
use App\Services\Health\HealthChecker;
use Illuminate\Http\JsonResponse;

/**
 * Endpoints operacionales de health (RF-OBS-131 — Fase 13 PR2).
 *
 * Tres rutas, tres propósitos distintos:
 *  - `/health/live` — liveness. SIEMPRE 200 si el proceso responde.
 *    Útil para Kubernetes liveness probes y para distinguir "app
 *    caída" de "app sin deps".
 *  - `/health/ready` — readiness. 200 si los checks CRÍTICOS pasan,
 *    503 si alguno falla. Útil para que el orquestador (Caddy /
 *    Kubernetes) retire la instancia del pool.
 *  - `/health` — payload completo (incluye checks informativos). Útil
 *    para dashboards ops y runbooks. Status code refleja el agregado.
 *
 * NOTA: `/up` (Laravel built-in) sigue existiendo como alias histórico.
 * No lo reemplazamos para no romper deployments existentes.
 */
final class HealthController extends Controller
{
    public function __construct(
        private readonly DatabaseCheck $database,
        private readonly StorageCheck $storage,
        private readonly QueueCheck $queue,
    ) {}

    private function checker(): HealthChecker
    {
        return new HealthChecker($this->database, $this->storage, $this->queue);
    }

    public function live(): JsonResponse
    {
        return response()->json(data: [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function ready(): JsonResponse
    {
        $payload = $this->checker()->run();

        return response()->json(
            data: $payload,
            status: $payload['healthy'] ? 200 : 503,
        );
    }

    public function report(): JsonResponse
    {
        $payload = $this->checker()->run();

        return response()->json(
            data: $payload,
            status: $payload['healthy'] ? 200 : 503,
        );
    }
}
