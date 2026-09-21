<?php

declare(strict_types=1);

namespace App\Services\Health\Checks;

use App\Services\Health\Contracts\HealthCheck;
use App\Services\Health\HealthCheckResult;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Check del filesystem configurado como default (`FILESYSTEM_DISK`).
 *
 * Escribe, lee y borra un archivo sentinel para verificar que el
 * disco es escribible. CRÍTICO — sin storage no se suben imágenes de
 * productos ni se generan PDFs de facturas.
 */
final class StorageCheck implements HealthCheck
{
    private const SENTINEL_PATH = 'health-check-sentinel.txt';

    public function name(): string
    {
        return 'storage';
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        $start = hrtime(as_number: true);

        try {
            /** @var Filesystem $disk */
            $disk = Storage::disk();

            $written = $disk->put(self::SENTINEL_PATH, 'ok');
            if ($written === false) {
                return HealthCheckResult::fail(
                    name: $this->name(),
                    message: 'put() returned false on disk "'.$disk->getConfig()['driver'].'".',
                );
            }

            $contents = $disk->get(self::SENTINEL_PATH);
            if ($contents !== 'ok') {
                return HealthCheckResult::fail(
                    name: $this->name(),
                    message: 'Sentinel roundtrip mismatch.',
                );
            }

            $disk->delete(self::SENTINEL_PATH);

            $latencyMs = (hrtime(as_number: true) - $start) / 1_000_000;

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
