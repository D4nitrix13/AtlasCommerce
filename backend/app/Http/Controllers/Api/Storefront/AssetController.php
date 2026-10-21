<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Assets\StorageKeyDeriver;
use App\Services\Assets\UrlSigner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proxy de assets del tenant desde el FTP al browser.
 *
 * Los assets del tenant (logos, favicons, fotos de productos) viven
 * en el disk `ftp` bajo paths tipo:
 *
 *     tenants/{tenant-slug}/branding/logo.webp
 *     tenants/{tenant-slug}/products/{product-slug}/{hash}.{ext}
 *
 * El browser no puede pedirlos directo al FTP (no es HTTP, no es
 * publico, etc.), entonces este controller hace de proxy:
 *
 *     GET /api/storefront/assets/{path...}
 *
 * El tenant se resuelve via `store.domain` middleware y se valida
 * que el `path` solicitado vive bajo el namespace del tenant activo
 * o bajo `shared/` (assets de marca compartida). Esto evita que un
 * tenant sirva accidentalmente un asset de otro tenant.
 *
 * URL signing (Fase 3):
 *   Cuando `config('assets.url_signing.required') === true`, el
 *   controller exige query params `expires=<ts>&sig=<hex>` firmadas
 *   con HMAC-SHA256 y rechaza con 403 si faltan, expiraron, o no
 *   matchean. Default false (Fase 6 flipea post-backfill).
 *
 * Cache (Fase 3):
 *   El contenido binario se cachea en el store configurado por
 *   `config('assets.cache_store')` (default `redis`) con TTL de
 *   `config('assets.cache_ttl')` segundos (default 300, 5min). Si
 *   el archivo cambia en FTP, expira despues del TTL y se regenera.
 *   Para invalidar inmediatamente, el command `assets:invalidate-cache`
 *   (TBD) borra todas las claves con prefijo `ftp-asset:`.
 *
 * Enumeration logging (Fase 3):
 *   404s y malformed paths emiten logs estructurados con
 *   `asset.not_found` y `asset.path.malformed` respectivamente (hash
 *   SHA-256 + tenant_slug + client_ip + user_agent truncado). Esta
 *   traza es la señal para detectar y alertar sobre ataques de
 *   enumeración de URLs por terceros.
 *
 * Headers de respuesta:
 *   - Content-Type:    inferido de la extension
 *   - Cache-Control:   public, max-age=31536000 (1 ano, los assets no cambian)
 *   - ETag:            md5 del contenido (browser valida con If-None-Match)
 *
 * Errores:
 *   - 403 Forbidden:   path intenta salir del namespace del tenant,
 *                      o firma HMAC invalida/expirada (cuando
 *                      `url_signing.required`).
 *   - 404 Not Found:   archivo no existe en el FTP (logueado como
 *                      `asset.not_found`).
 */
class AssetController extends Controller
{
    /**
     * Prefijo de las claves de cache del contenido binario de un asset.
     * Sufijado con sha256 del path normalizado. Otros comandos (ej.
     * `assets:invalidate-cache`) pueden usar este prefijo para FLUSH.
     */
    private const CACHE_KEY_PREFIX = 'ftp-asset:';

    public function __construct(
        public StorageKeyDeriver $storageKeyDeriver,
        public UrlSigner $urlSigner,
    ) {}

    /**
     * Servir un asset del FTP al browser.
     *
     * Path params:
     *   - path (string): path relativo al FTP_ROOT, ej. tenants/panda-estampados/branding/logo.webp
     *
     * Query params (solo si url_signing.required):
     *   - expires (int): UNIX timestamp de expiración.
     *   - sig (string): HMAC-SHA256 hex sobre `path|expires`.
     *
     * Response:
     *   - 200 con el contenido binario del asset
     *   - 304 Not Modified si el ETag coincide con If-None-Match
     *   - 403 si el path no pertenece al tenant activo ni a shared/,
     *          o si falla la verificación HMAC
     *   - 404 si el archivo no existe (logueado estructuradamente)
     */
    public function show(Request $request, string $path): Response
    {
        $store = $request->attributes->get('store');
        $tenantSlug = $store?->slug;

        // Normalizar el path: remover leading slashes, rechazar '..' y null bytes.
        $normalizedPath = $this->normalizePath(path: $path);

        if ($normalizedPath === null) {
            return response(content: 'Invalid path', status: 403);
        }

        // Validar que el path pertenece al tenant activo o a shared/.
        // Formatos validos:
        //   - tenants/{slug}/...  (assets de un tenant especifico)
        //   - shared/...           (assets de marca compartida, fallback)
        if (! $this->pathBelongsToTenant(path: $normalizedPath, tenantSlug: $tenantSlug)) {
            return response(content: 'Forbidden', status: 403);
        }

        // Soft-mode path validation. Si strict=true (post Fase 5 backfill),
        // rechaza con 403. Si strict=false (default Fase 2), loguea warning
        // estructurado y sigue sirviendo — el log es la señal para
        // priorizar el backfill de Fase 5.
        if (! $this->storageKeyDeriver->validate(path: $normalizedPath)) {
            if (config(key: 'assets.path_validation.strict') === true) {
                abort(code: 403, message: 'Malformed asset path.');
            }

            logger()->warning(message: 'asset.path.malformed', context: [
                'hash' => hash(algo: 'sha256', data: $normalizedPath),
                'tenant_slug' => $tenantSlug,
                'strict_mode' => false,
            ]);
        }

        // URL signing (Fase 3): si el flag está encendido, exigir firma
        // HMAC válida. Gated por config para permitir rollout gradual
        // (default off — Fase 6 flipea a true post-backfill).
        if (config(key: 'assets.url_signing.required') === true) {
            $expires = $request->query(key: 'expires');
            $signature = $request->query(key: 'sig');

            // Laravel devuelve `mixed` desde `query()`; hacemos
            // narrow a `?string` para poder pasarlo a la firma del
            // verify() con tipos estrictos.
            $expiresValue = is_string(value: $expires) ? $expires : null;
            $signatureValue = is_string(value: $signature) ? $signature : null;

            if ($expiresValue === null || $signatureValue === null) {
                abort(code: 403, message: 'Missing signature.');
            }

            if (! $this->urlSigner->verify(
                path: $normalizedPath,
                expires: $expiresValue,
                signature: $signatureValue,
            )) {
                abort(code: 403, message: 'Invalid or expired signature.');
            }
        }

        $disk = Storage::disk(name: config(key: 'assets.disk', default: 'ftp'));

        if (! $disk->exists(path: $normalizedPath)) {
            // Structured 404 logging (Fase 3): emitir warning con
            // contexto mínimo (hash del path + tenant + IP + UA
            // truncado) para detección de enumeration attacks. Ver
            // docs/architecture/assets-naming-convention.md §6.3.3.
            logger()->warning(message: 'asset.not_found', context: [
                'hash' => hash(algo: 'sha256', data: $normalizedPath),
                'tenant_slug' => $tenantSlug,
                'client_ip' => $request->ip(),
                'user_agent' => substr(
                    string: (string) ($request->userAgent() ?? 'unknown'),
                    offset: 0,
                    length: 200,
                ),
            ]);

            return response(content: 'Not found', status: 404);
        }

        // Cache en el store configurado (default redis) con TTL de
        // `config('assets.cache_ttl')` segundos (default 300, 5min).
        // La key usa sha256 del path para longitud fija y evitar
        // colisiones. Cachea el contenido binario para eliminar
        // latencia del FTP en requests repetidos.
        $cacheKey = self::CACHE_KEY_PREFIX.hash(algo: 'sha256', data: $normalizedPath);
        $ttl = (int) config(key: 'assets.cache_ttl');
        $contents = Cache::store(config(key: 'assets.cache_store'))->remember(
            key: $cacheKey,
            ttl: $ttl,
            callback: fn (): string|false => $disk->get(path: $normalizedPath),
        );

        if ($contents === false) {
            return response(content: 'Cache miss - FTP read failed', status: 500);
        }

        $mimeType = $disk->mimeType(path: $normalizedPath) ?? 'application/octet-stream';
        $etag = '"'.md5($contents).'"';

        // Soporte de If-None-Match para 304 Not Modified.
        $ifNoneMatch = $request->headers->get(key: 'If-None-Match');
        if ($ifNoneMatch !== null && $this->etagMatches(ifNoneMatch: $ifNoneMatch, etag: $etag)) {
            return response(status: 304)
                ->header(key: 'ETag', values: $etag);
        }

        return response(content: $contents, status: 200)
            ->header(key: 'Content-Type', values: $mimeType)
            ->header(key: 'Cache-Control', values: 'public, max-age=31536000, immutable')
            ->header(key: 'ETag', values: $etag);
    }

    /**
     * Normalizar y validar el path del asset.
     *
     * Reglas:
     *   - Remover leading slashes.
     *   - Rechazar '..' (path traversal).
     *   - Rechazar null bytes (security).
     *   - Rechazar si esta vacio despues de normalizar.
     *
     * Returns: path normalizado, o null si invalido.
     */
    private function normalizePath(string $path): ?string
    {
        $path = ltrim(string: $path, characters: '/');

        // Security: rechazar path traversal y null bytes.
        if (str_contains(haystack: $path, needle: '..') || str_contains(haystack: $path, needle: "\0")) {
            return null;
        }

        if ($path === '') {
            return null;
        }

        return $path;
    }

    /**
     * Validar que el path pertenece al tenant activo o a shared/.
     *
     * Paths validos:
     *   - tenants/{tenantSlug}/...
     *   - shared/...
     *
     * Si no pertenece al tenant activo ni a shared/, devuelve false
     * (403). Esto previene que un request a panda-estampados.com
     * sirva assets de kitsune (los 2 viven en la misma instancia).
     */
    private function pathBelongsToTenant(string $path, ?string $tenantSlug): bool
    {
        // shared/ es accesible para cualquier tenant (assets de marca compartida).
        if (str_starts_with(haystack: $path, needle: 'shared/')) {
            return true;
        }

        if ($tenantSlug === null) {
            return false;
        }

        // tenants/{slug}/... solo si el slug del path matchea el tenant activo.
        $prefix = 'tenants/'.$tenantSlug.'/';

        return str_starts_with(haystack: $path, needle: $prefix);
    }

    /**
     * Comparar el header If-None-Match con el ETag generado.
     *
     * Soporta los formatos:
     *   - "etag"
     *   - W/"etag"  (weak validator)
     *   - *         (match any)
     */
    private function etagMatches(string $ifNoneMatch, string $etag): bool
    {
        if ($ifNoneMatch === '*') {
            return true;
        }

        // Strip W/ prefix del ETag del request (weak comparison).
        $normalizedRequestEtag = ltrim(string: $ifNoneMatch, characters: 'W/');

        return $normalizedRequestEtag === $etag;
    }
}
