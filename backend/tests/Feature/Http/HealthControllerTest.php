<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests del `HealthController` (RF-OBS-131 — Fase 13 PR2).
 *
 * Verifica:
 *  - `/up` siempre 200 (liveness — Caddy lo usa como health_uri).
 *  - `/health/live` siempre 200 (alias semántico).
 *  - `/health/ready` 200 cuando todos los checks críticos pasan.
 *  - `/health/ready` 503 cuando un check crítico falla.
 *  - `/health` devuelve el payload completo con todos los checks.
 *  - Los headers de correlación (Fase 13 PR1) están presentes.
 */
#[Group('observability')]
#[Group('health')]
final class HealthControllerTest extends TestCase
{
    #[Test]
    public function up_endpoint_returns_200_with_ok_status(): void
    {
        $response = $this->getJson(uri: '/up');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonStructure(['status', 'timestamp']);
    }

    #[Test]
    public function health_live_returns_200_with_ok_status(): void
    {
        $response = $this->getJson(uri: '/health/live');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonStructure(['status', 'timestamp']);
    }

    #[Test]
    public function health_ready_returns_200_when_all_critical_checks_pass(): void
    {
        $response = $this->getJson(uri: '/health/ready');

        $response->assertOk();
        $response->assertJsonPath('healthy', true);
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonStructure([
            'healthy',
            'status',
            'timestamp',
            'checks' => [
                'database' => ['name', 'healthy', 'latency_ms'],
                'storage' => ['name', 'healthy', 'latency_ms'],
                'queue' => ['name', 'healthy', 'latency_ms'],
            ],
        ]);
    }

    #[Test]
    public function health_ready_returns_503_when_database_check_fails(): void
    {
        // Simulamos DB caída forzando un query que falla.
        // Usamos un host inválido para la conexión de test.
        config()->set('database.connections.pgsql.host', '127.0.0.1');
        config()->set('database.connections.pgsql.port', 1); // puerto cerrado
        DB::purge('pgsql');

        $response = $this->getJson(uri: '/health/ready');

        $response->assertStatus(503);
        $response->assertJsonPath('healthy', false);
        $response->assertJsonPath('status', 'fail');
        $response->assertJsonPath('checks.database.healthy', false);
    }

    #[Test]
    public function health_report_includes_all_checks(): void
    {
        $response = $this->getJson(uri: '/health');

        $response->assertOk();
        $response->assertJsonStructure([
            'healthy',
            'status',
            'timestamp',
            'checks' => [
                'database',
                'storage',
                'queue',
            ],
        ]);
    }

    #[Test]
    public function health_endpoints_include_correlation_headers(): void
    {
        // Verifica que la integración con Fase 13 PR1 sigue funcionando
        // en los nuevos endpoints.
        $response = $this->withHeader(name: 'X-Request-Id', value: 'health-test-123')
            ->getJson(uri: '/health/live');

        $response->assertOk();
        $this->assertSame('health-test-123', $response->headers->get('X-Request-Id'));
        $this->assertNotNull($response->headers->get('traceparent'));
    }
}
