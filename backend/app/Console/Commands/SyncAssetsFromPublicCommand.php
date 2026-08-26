<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Sincroniza los assets de marca commiteados en `brand-assets/` al
 * FTP, usando la convencion documentada en
 * `docs/architecture/assets-naming-convention.md`.
 *
 * Source (en este repo, versionado en git):
 *     brand-assets/
 *         store/{slug}/logo.webp          # categoria A
 *         store/{slug}/favicon.webp       # categoria A
 *         shared/icons/social/*.webp      # categoria C
 *
 * Destino (en FTP, relativo a FTP_ROOT):
 *     tenants/{slug}/branding/logo.webp
 *     tenants/{slug}/branding/favicon.webp
 *     shared/icons/social/facebook.webp
 *
 * Uso:
 *     php artisan assets:sync-from-public                    # sincroniza todos los stores
 *     php artisan assets:sync-from-public --store=panda-estampados   # solo uno
 *     php artisan assets:sync-from-public --dry-run          # no escribe, solo reporta
 *     php artisan assets:sync-from-public --force            # sobrescribe archivos existentes
 *
 * Estructura esperada en `brand-assets/`:
 *     store/{slug}/logo.webp          (categoria A)
 *     store/{slug}/favicon.webp       (categoria A)
 *     store/{slug}/og-image.webp      (categoria A, futuro)
 *     store/{slug}/cover.webp         (categoria A, futuro)
 *     store/{slug}/login-logo.webp    (categoria A, futuro)
 *     shared/icons/social/facebook.webp  (categoria C)
 *     shared/icons/social/instagram.webp (categoria C)
 *
 * Los archivos que no matcheen estos paths se ignoran silenciosamente
 * (ej. brand-assets/frontend/amico/auth/*.svg son categoria B y NO
 * se suben al FTP — se sirven desde frontend/public/ directo).
 */
final class SyncAssetsFromPublicCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'assets:sync-from-public
        {--store= : Slug del store especifico a sincronizar (default: todos)}
        {--dry-run : Solo reporta lo que haria, sin escribir en el FTP}
        {--force : Sobrescribe archivos existentes en el FTP}';

    /**
     * @var string
     */
    protected $description = 'Migra assets de marca commiteados en brand-assets/ al FTP bajo la convencion tenants/{slug}/branding/ y shared/';

    /**
     * Directorio base de los assets de marca (relativo a la raiz del repo).
     */
    private const BRAND_ASSETS_BASE = 'brand-assets';

    /**
     * Mapping de paths fuente (relativos a brand-assets/) a paths destino
     * (relativos a FTP_ROOT).
     *
     * Categoria A — per-tenant, usa placeholder {slug} en el destino.
     * Categoria C — compartido, sin placeholder.
     *
     * @var array<string, string>
     */
    private const ASSET_MAPPINGS = [
        // Categoria A — per-tenant
        'store/{slug}/logo.webp' => 'tenants/{slug}/branding/logo.webp',
        'store/{slug}/favicon.webp' => 'tenants/{slug}/branding/favicon.webp',
        'store/{slug}/og-image.webp' => 'tenants/{slug}/branding/og-image.webp',
        'store/{slug}/cover.webp' => 'tenants/{slug}/branding/cover.webp',
        'store/{slug}/login-logo.webp' => 'tenants/{slug}/branding/login-logo.webp',

        // Categoria C — compartido
        'shared/icons/social/facebook.webp' => 'shared/icons/social/facebook.webp',
        'shared/icons/social/instagram.webp' => 'shared/icons/social/instagram.webp',
    ];

    public function handle(): int
    {
        $storeFilter = $this->option(key: 'store');
        $isDryRun = (bool) $this->option(key: 'dry-run');
        $isForce = (bool) $this->option(key: 'force');

        if ($isDryRun) {
            $this->warn(string: 'DRY-RUN mode: no se escribira nada en el FTP.');
        }

        $disk = Storage::disk(name: 'ftp');
        $basePath = base_path(path: self::BRAND_ASSETS_BASE);

        if (! is_dir(filename: $basePath)) {
            $this->error(string: 'Directorio '.$basePath.' no existe.');

            return self::FAILURE;
        }

        $stats = [
            'uploaded' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        // Procesar categoria A (per-tenant) primero.
        foreach ($this->detectStores(basePath: $basePath.'/store', storeFilter: $storeFilter) as $storeSlug) {
            $this->line(string: "Store: {$storeSlug}");

            foreach (self::ASSET_MAPPINGS as $sourcePattern => $destPattern) {
                if (! str_contains(haystack: $sourcePattern, needle: '{slug}')) {
                    continue;
                }

                $sourcePath = str_replace(search: '{slug}', replace: $storeSlug, subject: $sourcePattern);
                $destPath = str_replace(search: '{slug}', replace: $storeSlug, subject: $destPattern);

                $this->syncOne(
                    disk: $disk,
                    basePath: $basePath,
                    sourcePath: $sourcePath,
                    destPath: $destPath,
                    isDryRun: $isDryRun,
                    isForce: $isForce,
                    stats: $stats,
                );
            }
        }

        // Procesar categoria C (shared) — solo si no hay filtro de store.
        if ($storeFilter === null) {
            $this->line(string: 'Shared:');

            foreach (self::ASSET_MAPPINGS as $sourcePattern => $destPattern) {
                if (str_contains(haystack: $sourcePattern, needle: '{slug}')) {
                    continue;
                }

                $this->syncOne(
                    disk: $disk,
                    basePath: $basePath,
                    sourcePath: $sourcePattern,
                    destPath: $destPattern,
                    isDryRun: $isDryRun,
                    isForce: $isForce,
                    stats: $stats,
                );
            }
        }

        $this->newLine();
        $this->info(string: "Resumen: {$stats['uploaded']} subidos, {$stats['skipped']} saltados, {$stats['errors']} errores.");

        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Sincronizar un archivo individual.
     *
     * @param  array{uploaded: int, skipped: int, errors: int}  $stats  (por referencia)
     */
    private function syncOne(
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        string $basePath,
        string $sourcePath,
        string $destPath,
        bool $isDryRun,
        bool $isForce,
        array &$stats,
    ): void {
        $sourceFullPath = $basePath.'/'.$sourcePath;

        if (! file_exists(filename: $sourceFullPath)) {
            // No es un error — un store puede no tener todos los formatos.
            return;
        }

        if ($disk->exists(path: $destPath) && ! $isForce) {
            $this->line(string: "  - {$destPath} ya existe (usar --force para sobrescribir)");
            $stats['skipped']++;

            return;
        }

        if ($isDryRun) {
            $this->line(string: "  - DRY-RUN: subiria {$sourcePath} -> {$destPath}");
            $stats['uploaded']++;

            return;
        }

        try {
            $contents = file_get_contents(filename: $sourceFullPath);

            if ($contents === false) {
                $this->error(string: "  - No se pudo leer {$sourceFullPath}");
                $stats['errors']++;

                return;
            }

            $disk->put(path: $destPath, contents: $contents);
            $this->info(string: "  - Subido: {$destPath} (".strlen(string: $contents).' bytes)');
            $stats['uploaded']++;
        } catch (Throwable $e) {
            $this->error(string: "  - Error subiendo {$destPath}: {$e->getMessage()}");
            $stats['errors']++;
        }
    }

    /**
     * Detectar stores desde los subdirectorios de brand-assets/store/.
     *
     * @return array<int, string>
     */
    private function detectStores(string $basePath, ?string $storeFilter): array
    {
        if (! is_dir(filename: $basePath)) {
            return [];
        }

        $all = array_values(array: scandir(directory: $basePath) ?: []);

        $stores = array_filter(
            array: $all,
            callback: fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir(filename: $basePath.'/'.$entry),
        );

        if ($storeFilter !== null) {
            $stores = array_filter(
                array: $stores,
                callback: fn (string $slug): bool => $slug === $storeFilter,
            );

            if ($stores === []) {
                $this->error(string: "Store '{$storeFilter}' no encontrado en brand-assets/store/");
            }
        }

        return array_values(array: $stores);
    }
}
