<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Health;

use App\Services\Health\HealthCheckResult;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests de `App\Services\Health\HealthCheckResult` (RF-OBS-131).
 *
 * Cubre:
 *  - Factory methods (ok, fail, fromThrowable).
 *  - `toArray()` omite `message` cuando es null.
 *  - Latencia redondeada a 2 decimales.
 */
#[Group('observability')]
#[Group('health')]
final class HealthCheckResultTest extends TestCase
{
    #[Test]
    public function ok_factory_returns_healthy_result(): void
    {
        $result = HealthCheckResult::ok(name: 'database', latencyMs: 12.34, message: 'connected');

        $this->assertSame('database', $result->name);
        $this->assertTrue($result->healthy);
        $this->assertSame('connected', $result->message);
        $this->assertSame(12.34, $result->latencyMs);
    }

    #[Test]
    public function fail_factory_returns_unhealthy_result(): void
    {
        $result = HealthCheckResult::fail(name: 'storage', message: 'disk full', latencyMs: 5.0);

        $this->assertSame('storage', $result->name);
        $this->assertFalse($result->healthy);
        $this->assertSame('disk full', $result->message);
    }

    #[Test]
    public function from_throwable_captures_exception_class_and_message(): void
    {
        $result = HealthCheckResult::fromThrowable(
            name: 'database',
            e: new RuntimeException(message: 'connection refused'),
            latencyMs: 1.5,
        );

        $this->assertSame('database', $result->name);
        $this->assertFalse($result->healthy);
        $this->assertStringContainsString('RuntimeException', (string) $result->message);
        $this->assertStringContainsString('connection refused', (string) $result->message);
    }

    #[Test]
    public function to_array_omits_message_when_null(): void
    {
        $result = HealthCheckResult::ok(name: 'queue', latencyMs: 0.5);

        $array = $result->toArray();

        $this->assertArrayNotHasKey('message', $array);
        $this->assertSame('queue', $array['name']);
        $this->assertTrue($array['healthy']);
        $this->assertSame(0.5, $array['latency_ms']);
    }

    #[Test]
    public function to_array_rounds_latency_to_two_decimals(): void
    {
        $result = HealthCheckResult::ok(name: 'database', latencyMs: 17.061234);

        $array = $result->toArray();

        $this->assertSame(17.06, $array['latency_ms']);
    }

    #[Test]
    public function to_array_includes_message_when_present(): void
    {
        $result = HealthCheckResult::ok(name: 'queue', latencyMs: 1.0, message: 'size: 5');

        $array = $result->toArray();

        $this->assertSame('size: 5', $array['message']);
    }
}
