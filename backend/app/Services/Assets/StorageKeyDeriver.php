<?php

declare(strict_types=1);

namespace App\Services\Assets;

use InvalidArgumentException;

/**
 * Genera y valida paths de almacenamiento de assets (categoría A).
 *
 * Convenciones (ver docs/architecture/assets-naming-convention.md §2):
 *   - Tenant: `tenants/{slug}/{collection}/{entity}/{hash}.{ext}`
 *   - Shared: `shared/{entity}/{hash}.{ext}`
 *
 * El hash es `substr(sha256($content), 0, 12)` — las primeras 12 chars
 * hex del SHA-256 del binario. Esto da 48 bits ≈ 2.81e14 combinaciones,
 * suficiente para anti-enumeración dentro de un tenant; además garantiza
 * longitud acotada del path. El nombre del archivo subido NUNCA se usa
 * en el path (ver §2.3 del doc) — siempre el hash del contenido.
 *
 * Esta clase es **pura**: sin I/O, sin Storage facade, sin globals.
 * Solo computa y valida strings. Eso permite usarla desde commands de
 * backfill (Fase 5), desde controllers (Fase 2/3), y desde tests sin
 * levantar contexto de Laravel.
 */
final class StorageKeyDeriver
{
    /**
     * Algoritmo usado para `imagen-hash`.
     */
    public const HASH_ALGO = 'sha256';

    /**
     * Longitud del prefijo hash en el path (chars hex). 12 chars =
     * 48 bits ≈ 2.81e14 valores únicos por tenant.
     */
    public const HASH_LENGTH = 12;

    /**
     * Extensiones de archivo soportadas (canonicalizadas a lowercase).
     */
    public const EXTENSIONS = ['webp', 'png', 'jpg', 'jpeg', 'svg'];

    /**
     * Colecciones válidas dentro del namespace de un tenant.
     */
    public const COLLECTIONS = ['products', 'branding', 'marketing'];

    /**
     * Regex para paths bajo `tenants/{slug}/`.
     *
     * Grupos nombrados:
     *   - slug: kebab-case del tenant (1-64 chars, lowercase a-z0-9-)
     *   - collection: products | branding | marketing
     *   - entity: kebab-case del asset (1-128 chars)
     *   - hash: 12 chars hex (SHA-256 prefix)
     *   - ext: webp | png | jpg | jpeg | svg
     */
    public const TENANT_PATH_PATTERN = '#^tenants/(?P<slug>[a-z0-9-]{1,64})/(?P<collection>products|branding|marketing)/(?P<entity>[a-z0-9-]{1,128})/(?P<hash>[a-f0-9]{12})\.(?P<ext>webp|png|jpg|jpeg|svg)$#';

    /**
     * Regex para paths bajo `shared/`.
     *
     * Grupos nombrados:
     *   - entity: kebab-case del asset (1-128 chars)
     *   - hash: 12 chars hex (SHA-256 prefix)
     *   - ext: webp | png | jpg | jpeg | svg
     */
    public const SHARED_PATH_PATTERN = '#^shared/(?P<entity>[a-z0-9-]{1,128})/(?P<hash>[a-f0-9]{12})\.(?P<ext>webp|png|jpg|jpeg|svg)$#';

    /**
     * Derivar el path de storage para un asset de tenant.
     *
     * @param  string  $tenantSlug  kebab-case slug del tenant.
     * @param  string  $collection  products | branding | marketing.
     * @param  string  $entity  kebab-case entity (product slug, asset type, campaign slug).
     * @param  string  $content  contenido binario del archivo (usado para hashear).
     * @param  string  $extension  extensión canonicalizada a lowercase.
     * @return string path `tenants/{slug}/{collection}/{entity}/{hash}.{ext}`.
     *
     * @throws InvalidArgumentException si algún componente no matchea las reglas.
     */
    public function derive(
        string $tenantSlug,
        string $collection,
        string $entity,
        string $content,
        string $extension,
    ): string {
        if ($content === '') {
            throw new InvalidArgumentException(message: 'content must not be empty.');
        }

        if (preg_match(pattern: '#^[a-z0-9-]{1,64}$#', subject: $tenantSlug) !== 1) {
            throw new InvalidArgumentException(message: sprintf('tenantSlug "%s" must match [a-z0-9-]{1,64}.', $tenantSlug));
        }

        if (in_array(needle: $collection, haystack: self::COLLECTIONS, strict: true) === false) {
            throw new InvalidArgumentException(message: sprintf('collection "%s" must be one of: %s.', $collection, implode(separator: ', ', array: self::COLLECTIONS)));
        }

        if (preg_match(pattern: '#^[a-z0-9-]{1,128}$#', subject: $entity) !== 1) {
            throw new InvalidArgumentException(message: sprintf('entity "%s" must match [a-z0-9-]{1,128}.', $entity));
        }

        $extension = strtolower(string: $extension);

        if (in_array(needle: $extension, haystack: self::EXTENSIONS, strict: true) === false) {
            throw new InvalidArgumentException(message: sprintf('extension "%s" must be one of: %s.', $extension, implode(separator: ', ', array: self::EXTENSIONS)));
        }

        $hash = substr(string: hash(algo: self::HASH_ALGO, data: $content), offset: 0, length: self::HASH_LENGTH);

        return sprintf('tenants/%s/%s/%s/%s.%s', $tenantSlug, $collection, $entity, $hash, $extension);
    }

    /**
     * Validar que un path matchea el formato de la convención.
     *
     * Acepta paths de tenant (`tenants/{slug}/...`) y shared (`shared/...`).
     * Devuelve `false` para path vacío, traversal, slashes extra, o
     * cualquier desviación del regex — el caller decide si abortar o
     * solo loguear (Fase 2 soft mode).
     */
    public function validate(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        return preg_match(pattern: self::TENANT_PATH_PATTERN, subject: $path) === 1
            || preg_match(pattern: self::SHARED_PATH_PATTERN, subject: $path) === 1;
    }

    /**
     * Extraer los componentes de un path válido.
     *
     * Útil para tooling de migración/auditoría (Fase 4/5). Devuelve
     * `null` si el path no matchea ninguno de los patrones.
     *
     * @return null|array{
     *     tenant_slug?: string,
     *     collection?: string,
     *     entity: string,
     *     hash: string,
     *     ext: string,
     * }
     */
    public function extractComponents(string $path): ?array
    {
        if ($path === '') {
            return null;
        }

        if (preg_match(pattern: self::TENANT_PATH_PATTERN, subject: $path, matches: $matches) === 1) {
            return [
                'tenant_slug' => $matches['slug'],
                'collection' => $matches['collection'],
                'entity' => $matches['entity'],
                'hash' => $matches['hash'],
                'ext' => $matches['ext'],
            ];
        }

        if (preg_match(pattern: self::SHARED_PATH_PATTERN, subject: $path, matches: $matches) === 1) {
            return [
                'entity' => $matches['entity'],
                'hash' => $matches['hash'],
                'ext' => $matches['ext'],
            ];
        }

        return null;
    }
}
