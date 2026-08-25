<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
 * público, etc.), entonces este controller hace de proxy:
 *
 *     GET /api/storefront/assets/{path...}
 *
 * El tenant se resuelve via `store.domain` middleware y se valida
 * que el `path` solicitado vive bajo el namespace del tenant activo
 * o bajo `shared/` (assets de marca compartida). Esto evita que un
 * tenant sirva accidentalmente un asset de otro tenant.
 *
 * Headers de respuesta:
 *   - Content-Type:    inferido de la extension
 *   - Cache-Control:   public, max-age=31536000 (1 ano, los assets no cambian)
 *   - ETag:            md5 del contenido (browser valida con If-None-Match)
 *
 * Errores:
 *   - 403 Forbidden:   path intenta salir del namespace del tenant
 *   - 404 Not Found:   archivo no existe en el FTP
 */
class AssetController extends Controller
{
    /**
     * Servir un asset del FTP al browser.
     *
     * Path params:
     *   - path (string): path relativo al FTP_ROOT, ej. tenants/panda-estampados/branding/logo.webp
     *
     * Response:
     *   - 200 con el contenido binario del asset
     *   - 304 Not Modified si el ETag coincide con If-None-Match
     *   - 403 si el path no pertenece al tenant activo ni a shared/
     *   - 404 si el archivo no existe
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

        $disk = Storage::disk(name: 'ftp');

        if (! $disk->exists(path: $normalizedPath)) {
            return response(content: 'Not found', status: 404);
        }

        $contents = $disk->get(path: $normalizedPath);
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
