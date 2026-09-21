<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Health;

use App\Services\Health\Contracts\HealthCheck;
use App\Services\Health\HealthChecker;
use App\Services\Health\HealthCheckResult;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests del orquestador `HealthChecker` (RF-OBS-131 — Fase 13 PR2).
 *
 * Verifica la lógica de agregado: status global es `fail` si CUALQUIER
 * check CRÍTICO falla, pero los checks informativos (no críticos)
 * solo aparecen en el payload sin afectar el status.
 *
 * Usa anonymous classes que implementan `HealthCheck` en lugar de
 * mocks — los checks concretos son `final` y no se pueden mockear,
 * pero tampoco necesitamos mockearlos: la lógica del orquestador
 * solo depende del contrato `HealthCheck`.
 */
#[Group('observability')]
#[Group('health')]
final class HealthCheckerTest extends TestCase
{
    /**
     * Helper: crea un HealthCheck anónimo con nombre, criticidad y resultado fijo.
     */
    private function fakeCheck(string $name, bool $critical, HealthCheckResult $result): HealthCheck
    {
        return new class($name, $critical, $result) implements HealthCheck
        {
            public function __construct(
                private readonly string $n,
                private readonly bool $c,
                private readonly HealthCheckResult $r,
            ) {}

            public function name(): string
            {
                return $this->n;
            }

            public function isCritical(): bool
            {
                return $this->c;
            }

            public function run(): HealthCheckResult
            {
                return $this->r;
            }
        };
    }

    #[Test]
    public function it_returns_healthy_when_all_critical_checks_pass(): void
    {
        $db = $this->fakeCheck('database', true, HealthCheckResult::ok('database', 5.0));
        $storage = $this->fakeCheck('storage', true, HealthCheckResult::ok('storage', 3.0));
        $queue = $this->fakeCheck('queue', false, HealthCheckResult::ok('queue', 1.0));

        $checker = new HealthChecker($db, $storage, $queue);
        $result = $checker->run();

        $this->assertTrue($result['healthy']);
        $this->assertSame('ok', $result['status']);
        $this->assertArrayHasKey('database', $result['checks']);
        $this->assertArrayHasKey('storage', $result['checks']);
        $this->assertArrayHasKey('queue', $result['checks']);
    }

    #[Test]
    public function it_returns_unhealthy_when_critical_check_fails(): void
    {
        $db = $this->fakeCheck(
            'database',
            true,
            HealthCheckResult::fail('database', 'connection refused', 100.0),
        );
        $storage = $this->fakeCheck('storage', true, HealthCheckResult::ok('storage'));
        $queue = $this->fakeCheck('queue', false, HealthCheckResult::ok('queue'));

        $checker = new HealthChecker($db, $storage, $queue);
        $result = $checker->run();

        $this->assertFalse($result['healthy']);
        $this->assertSame('fail', $result['status']);
        $this->assertFalse($result['checks']['database']['healthy']);
        $this->assertSame('connection refused', $result['checks']['database']['message']);
    }

    #[Test]
    public function it_returns_healthy_when_only_non_critical_check_fails(): void
    {
        $db = $this->fakeCheck('database', true, HealthCheckResult::ok('database'));
        $storage = $this->fakeCheck('storage', true, HealthCheckResult::ok('storage'));
        $queue = $this->fakeCheck(
            'queue',
            false,
            HealthCheckResult::fail('queue', 'queue unreachable'),
        );

        $checker = new HealthChecker($db, $storage, $queue);
        $result = $checker->run();

        $this->assertTrue($result['healthy']);
        $this->assertFalse($result['checks']['queue']['healthy']);
    }

    #[Test]
    public function is_ready_helper_returns_boolean(): void
    {
        $db = $this->fakeCheck('database', true, HealthCheckResult::ok('database'));
        $storage = $this->fakeCheck('storage', true, HealthCheckResult::ok('storage'));
        $queue = $this->fakeCheck('queue', false, HealthCheckResult::ok('queue'));

        $checker = new HealthChecker($db, $storage, $queue);

        $this->assertTrue($checker->isReady());
    }

    #[Test]
    public function it_includes_iso_timestamp_in_payload(): void
    {
        $db = $this->fakeCheck('database', true, HealthCheckResult::ok('database'));
        $storage = $this->fakeCheck('storage', true, HealthCheckResult::ok('storage'));
        $queue = $this->fakeCheck('queue', false, HealthCheckResult::ok('queue'));

        $checker = new HealthChecker($db, $storage, $queue);
        $result = $checker->run();

        $this->assertArrayHasKey('timestamp', $result);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result['timestamp']);
    }
}
