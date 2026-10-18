<?php

declare(strict_types=1);

namespace App\Services\Assets;

/**
 * Firma y verifica URLs de assets mediante HMAC-SHA256.
 *
 * Cada URL firmada embebe dos query params:
 *   - `expires=<unix_ts>` momento de expiración (UNIX timestamp en segundos).
 *   - `sig=<hex>`          firma HMAC-SHA256(secret, "path|expires").
 *
 * El `verify()` valida que la firma coincida con `hash_equals`
 * (timing-safe) y que la expiración sea estrictamente mayor al
 * `time()` actual. Si cualquiera falla, devuelve `false` — el caller
 * aborta con 403.
 *
 * Por qué HMAC y no una firma estática simple:
 *   - Timing-safe: la comparación con `hash_equals` evita ataques de
 *     canal lateral sobre la comparación de bytes.
 *   - Forward secrecy: rotar el `secret` invalida todas las URLs
 *     firmadas con el secret anterior sin tocar BD.
 *   - Stateless: no requiere tabla de revocación en BD; la expiración
 *     embebida en la URL es la fuente de verdad temporal.
 *
 * Esta clase es **pura**: sin I/O, sin facades, sin globals. El
 * secret y el TTL default se inyectan por constructor (DI). El caller
 * (controller) es responsable de resolver el secret desde
 * `config('assets.url_signing.secret') ?? config('app.key')` —
 * mantener el resolution en el controller evita acoplar `Config`
 * al servicio.
 */
final class UrlSigner
{
    /**
     * Algoritmo HMAC usado para firmar.
     */
    public const HMAC_ALGO = 'sha256';

    /**
     * Separador del payload canónico. Pipe (`|`) no es legal en
     * storage paths (`StorageKeyDeriver::EXTENSIONS` y slugs son
     * `[a-z0-9-]+`) — usarlo garantiza que `path|expires` se parsea
     * de forma única sin ambigüedad.
     */
    public const PAYLOAD_DELIMITER = '|';

    /**
     * TTL por defecto en segundos (5min). Configurable por instancia
     * vía constructor para que el caller (controller) lo levante de
     * `config('assets.url_signing.ttl')`.
     */
    public const DEFAULT_TTL_SECONDS = 300;

    /**
     * @param  string  $secret  clave compartida usada como HMAC secret.
     *                          Debe venir pre-resuelta por el caller
     *                          (no se accede a `config()` adentro).
     * @param  int  $defaultTtl  TTL por defecto en segundos. El método
     *                           `sign()` lo usa cuando el caller no
     *                           especifica un TTL explícito.
     */
    public function __construct(
        public readonly string $secret,
        public readonly int $defaultTtl = self::DEFAULT_TTL_SECONDS,
    ) {}

    /**
     * Firmar un path retornando el query string canónico.
     *
     * Formato: `"expires=<unix_ts>&sig=<hex>"`. El orden es estable
     * para que el parser del lado del request pueda reconstruir la
     * verificación independientemente del orden de query params.
     *
     * @param  string  $path  path canónico del asset (post-normalización
     *                        del controller). Sin query string.
     * @param  null|int  $ttl  TTL en segundos. Si es `null`, usa
     *                         `$defaultTtl`. Si es <= 0, se trata
     *                         como `null` (fallback al default).
     * @return string `"expires=<ts>&sig=<hex>"`.
     */
    public function sign(string $path, ?int $ttl = null): string
    {
        $effectiveTtl = ($ttl === null || $ttl <= 0)
            ? $this->defaultTtl
            : $ttl;

        $expires = time() + $effectiveTtl;
        $signature = $this->computeSignature(path: $path, expires: $expires);

        return sprintf('expires=%d&sig=%s', $expires, $signature);
    }

    /**
     * Verificar un path firmado contra su `expires` + `signature`.
     *
     * Triple check:
     *   1. `expires` es numérico positivo.
     *   2. `signature` no está vacío.
     *   3. `hash_equals` contra la firma computada
     *      (`HMAC-SHA256(secret, path|expires)`).
     *   4. `expires` es estrictamente mayor a `time()` (no expirado).
     *
     * Comparison timing-safe: la comparación de la firma usa
     * `hash_equals`, que tarda el mismo tiempo independiente de
     * cuántos bytes coincidan. Un atacante no puede inferir la firma
     * byte-a-byte midiendo latencia.
     *
     * @param  string  $path  path canónico del asset (mismo string
     *                        que se pasó a `sign()` — case-sensitive).
     * @param  string  $expires  valor del query param `expires`
     *                           (string numérico).
     * @param  string  $signature  valor del query param `sig` (hex).
     * @return bool `true` solo si los 4 checks pasan.
     */
    public function verify(string $path, string $expires, string $signature): bool
    {
        if ($expires === '' || ! is_numeric(value: $expires)) {
            return false;
        }

        $expiresInt = (int) $expires;

        if ($expiresInt <= 0) {
            return false;
        }

        if ($signature === '') {
            return false;
        }

        if (ctype_xdigit($signature) === false) {
            return false;
        }

        $expected = $this->computeSignature(path: $path, expires: $expiresInt);

        if (! hash_equals(known_string: $expected, user_string: $signature)) {
            return false;
        }

        return $expiresInt > time();
    }

    /**
     * Computar HMAC-SHA256 sobre el payload canónico `path|expires`.
     *
     * El payload canónico fija el orden de los componentes: `path`
     * primero, `expires` (numérico int) segundo. Eso garantiza que
     * sign + verify son consistentes: misma string en ambos lados
     * produce misma firma.
     */
    private function computeSignature(string $path, int $expires): string
    {
        $payload = $path.self::PAYLOAD_DELIMITER.$expires;

        return hash_hmac(
            algo: self::HMAC_ALGO,
            data: $payload,
            key: $this->secret,
        );
    }
}
