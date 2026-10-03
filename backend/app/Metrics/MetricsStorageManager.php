<?php

declare(strict_types=1);

namespace App\Metrics;

use Illuminate\Support\Manager;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APC;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Predis;
use Prometheus\Storage\Redis as RedisAdapter;

/**
 * Manager para construir el `Adapter` de storage de Prometheus según
 * `config/metrics.php`. Sigue el patrón `Illuminate\Support\Manager`
 * de Laravel — cada driver implementa un método propio.
 *
 * Backends soportados:
 *  - redis   (Predis client — recomendado para producción)
 *  - apcu    (storage per-process — solo dev local con un worker)
 *  - inmemory (storage in-process — solo tests unitarios)
 *
 * Default backend: `redis`. Configurable vía `METRICS_STORAGE`.
 */
final class MetricsStorageManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get(key: 'metrics.storage', default: 'redis');
    }

    /**
     * Construye el `CollectorRegistry` con el adapter configurado.
     */
    public function registry(): CollectorRegistry
    {
        return new CollectorRegistry($this->driver());
    }

    /**
     * Driver Redis vía ext-redis (phpredis).
     */
    public function createRedisDriver(): Adapter
    {
        $config = $this->config->get(key: 'metrics.redis', default: []);

        $adapter = new RedisAdapter([
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => (int) ($config['port'] ?? 6379),
            'password' => $config['password'] ?? null,
            'timeout' => (float) ($config['timeout'] ?? 0.1),
            'read_timeout' => 10,
            'persistent_connections' => false,
            'database' => (int) ($config['database'] ?? 0),
        ]);

        // RedisAdapter implements Adapter unconditionally, so the
        // runtime instanceof check is always true — kept as a
        // defensive null-check that PHPStan type system flags as
        // dead code. Removed to satisfy static analysis.
        return $adapter;
    }

    /**
     * Driver Redis vía Predis (pure PHP — usado cuando ext-redis no está disponible).
     */
    public function createPredisDriver(): Adapter
    {
        $config = $this->config->get(key: 'metrics.redis', default: []);

        $parameters = [
            'scheme' => 'tcp',
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => (int) ($config['port'] ?? 6379),
            'password' => $config['password'] ?? null,
            'timeout' => (float) ($config['timeout'] ?? 0.1),
        ];

        $client = new \Predis\Client(parameters: $parameters);

        $adapter = new Predis(client: $client);

        // Predis implements Adapter unconditionally, so the runtime
        // instanceof check is always true — removed to satisfy static
        // analysis.
        return $adapter;
    }

    /**
     * Driver APCu (storage per-process, no compartido entre php-fpm workers).
     */
    public function createApcuDriver(): Adapter
    {
        return new APC;
    }

    /**
     * Driver APCng (versión moderna de APC con soporte de tags).
     */
    public function createApcngDriver(): Adapter
    {
        return new APCng;
    }

    /**
     * Driver InMemory (storage in-process — solo para tests).
     */
    public function createInmemoryDriver(): Adapter
    {
        return new InMemory;
    }
}
