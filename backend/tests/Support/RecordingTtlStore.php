<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Cache\ArrayStore;

/**
 * `ArrayStore` que registra con qué TTL se escribió cada key.
 *
 * Existe para poder verificar que el controller aplica
 * `config('assets.cache_ttl')` sin depender de un store persistente.
 * Leer el TTL real exige Redis vivo, una key libre de corridas
 * anteriores y una tolerancia de drift por latencia — todo lo que hace
 * que el test sea no determinista. `ArrayStore` no expone TTL, así que
 * este double lo captura en el `put()` que dispara
 * `Repository::remember()`.
 *
 * Al ser un store en memoria, el estado muere con la app que Laravel
 * levanta para cada test: no arrastra claves entre tests ni entre
 * corridas de la suite.
 */
final class RecordingTtlStore extends ArrayStore
{
    /**
     * TTL en segundos por key, en orden de escritura.
     *
     * @var array<string, int>
     */
    private array $writes = [];

    /**
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds
     */
    public function put($key, $value, $seconds): bool
    {
        if (is_int(value: $seconds)) {
            $this->writes[$key] = $seconds;
        }

        return parent::put($key, $value, $seconds);
    }

    /**
     * TTL con el que se escribió la key, o `null` si nunca se escribió.
     */
    public function ttlFor(string $key): ?int
    {
        return $this->writes[$key] ?? null;
    }

    /**
     * Keys escritas, para afirmar que un store NO recibió escrituras.
     *
     * @return array<int, string>
     */
    public function writtenKeys(): array
    {
        return array_keys($this->writes);
    }
}
