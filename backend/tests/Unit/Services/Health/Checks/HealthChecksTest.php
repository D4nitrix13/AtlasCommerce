<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Health\Checks;

use App\Services\Health\Checks\DatabaseCheck;
use App\Services\Health\Checks\QueueCheck;
use App\Services\Health\Checks\StorageCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests de los checks individuales (RF-OBS-131 — Fase 13 PR2).
 *
 * Verifica happy path + failure path de cada check usando mocks del
 * facade subyacente.
 */
#[Group('observability')]
#[Group('health')]
final class HealthChecksTest extends TestCase
{
    protected function tearDown(): void
    {
        // El proyecto no usa MockeryPHPUnitIntegration en phpunit.xml,
        // así que los mocks de los facades (Storage, DB) se filtrarían
        // entre tests si no se cierran explícitamente.
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function database_check_returns_ok_when_select_one_succeeds(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('selectOne')
            ->once()
            ->with('SELECT 1 AS ok')
            ->andReturn((object) ['ok' => 1]);

        DB::shouldReceive('connection')
            ->once()
            ->andReturn($connection);

        $check = new DatabaseCheck;
        $result = $check->run();

        $this->assertSame('database', $result->name);
        $this->assertTrue($result->healthy);
        $this->assertTrue($check->isCritical());
    }

    #[Test]
    public function database_check_returns_fail_on_exception(): void
    {
        DB::shouldReceive('connection')
            ->once()
            ->andThrow(new RuntimeException('SQLSTATE[HY000] [2002] Connection refused'));

        $check = new DatabaseCheck;
        $result = $check->run();

        $this->assertSame('database', $result->name);
        $this->assertFalse($result->healthy);
        $this->assertStringContainsString('RuntimeException', (string) $result->message);
        $this->assertStringContainsString('Connection refused', (string) $result->message);
    }

    #[Test]
    public function database_check_returns_fail_on_unexpected_result(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('selectOne')
            ->once()
            ->with('SELECT 1 AS ok')
            ->andReturn((object) ['ok' => 99]);

        DB::shouldReceive('connection')
            ->once()
            ->andReturn($connection);

        $check = new DatabaseCheck;
        $result = $check->run();

        $this->assertFalse($result->healthy);
        $this->assertStringContainsString('unexpected', (string) $result->message);
    }

    #[Test]
    public function storage_check_returns_ok_on_roundtrip(): void
    {
        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->andReturn(true);
        $disk->shouldReceive('get')->once()->andReturn('ok');
        $disk->shouldReceive('delete')->once();
        Storage::shouldReceive('disk')->andReturn($disk);

        $check = new StorageCheck;
        $result = $check->run();

        $this->assertTrue($result->healthy);
        $this->assertTrue($check->isCritical());
    }

    #[Test]
    public function storage_check_returns_fail_on_put_error(): void
    {
        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('getConfig')->andReturn(['driver' => 'local']);
        Storage::shouldReceive('disk')->andReturn($disk);

        $check = new StorageCheck;
        $result = $check->run();

        $this->assertFalse($result->healthy);
        $this->assertStringContainsString('put()', (string) $result->message);
    }

    #[Test]
    public function storage_check_returns_fail_on_roundtrip_mismatch(): void
    {
        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->andReturn(true);
        $disk->shouldReceive('get')->once()->andReturn('corrupted');
        // delete NO se llama porque el check retorna fail antes.
        $disk->shouldReceive('delete')->zeroOrMoreTimes();
        Storage::shouldReceive('disk')->andReturn($disk);

        $check = new StorageCheck;
        $result = $check->run();

        $this->assertFalse($result->healthy);
        $this->assertStringContainsString('mismatch', (string) $result->message);
    }

    #[Test]
    public function queue_check_returns_ok_with_size(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('size')->once()->with('default')->andReturn(5);

        $factory = Mockery::mock(\Illuminate\Contracts\Queue\Factory::class);
        $factory->shouldReceive('connection')->once()->andReturn($connection);

        $check = new QueueCheck($factory);
        $result = $check->run();

        $this->assertTrue($result->healthy);
        $this->assertSame('queue size: 5', $result->message);
        $this->assertFalse($check->isCritical()); // queue NO es crítico
    }

    #[Test]
    public function queue_check_returns_ok_with_null_size(): void
    {
        // Drivers como sync devuelven null al size().
        $connection = Mockery::mock();
        $connection->shouldReceive('size')->once()->andReturn(null);

        $factory = Mockery::mock(\Illuminate\Contracts\Queue\Factory::class);
        $factory->shouldReceive('connection')->once()->andReturn($connection);

        $check = new QueueCheck($factory);
        $result = $check->run();

        $this->assertTrue($result->healthy);
        $this->assertStringContainsString('not measurable', (string) $result->message);
    }
}
