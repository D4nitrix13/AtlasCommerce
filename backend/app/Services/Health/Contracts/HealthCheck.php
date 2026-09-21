<?php

declare(strict_types=1);

namespace App\Services\Health\Contracts;

/**
 * Contrato para un check de salud individual.
 *
 * Cada check encapsula UNA dependencia (DB, storage, queue, etc.) y
 * devuelve un `HealthCheckResult` con estado y latencia.
 *
 * El `HealthChecker` corre todos los checks en orden. Los checks se
 * ejecutan en serie porque (a) son baratos (< 50ms cada uno) y (b) en
 * serie se evita ocultar fallos detrás de timeouts paralelos.
 */
interface HealthCheck
{
    /**
     * Nombre legible para logs y payloads JSON. Debe ser estable (no
     * localized) — lo consumen Prometheus labels y dashboards.
     */
    public function name(): string;

    /**
     * Ejecuta el check. No debe lanzar excepciones — debe capturar
     * errores y devolver un `HealthCheckResult` con `healthy=false` y
     * la excepción como `message`.
     */
    public function run(): \App\Services\Health\HealthCheckResult;

    /**
     * Si devuelve `true`, este check es CRÍTICO: su fallo hace que
     * `/health/ready` devuelva 503 y el orquestador retire la instancia
     * del pool. Si devuelve `false`, es informativo — se reporta en el
     * payload pero NO afecta el status code global.
     */
    public function isCritical(): bool;
}
