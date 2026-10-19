<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Assets\StorageKeyDeriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Sincroniza los assets de marca commiteados en `assets/` (raíz del
 * repo) al FTP, usando la convencion documentada en
 * `docs/architecture/assets-naming-convention.md`.
 *
 * Source (en este repo, versionado en git):
 *     assets/
 *         branding/{slug}/logo.webp          # categoria A
 *         branding/{slug}/favicon.webp       # categoria A
 *         shared/icons/social/*.webp         # categoria C
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
 * Estructura esperada en `assets/`:
 *     branding/{slug}/logo.webp          (categoria A)
 *     branding/{slug}/favicon.webp       (categoria A)
 *     branding/{slug}/og-image.webp      (categoria A, futuro)
 *     branding/{slug}/cover.webp         (categoria A, futuro)
 *     branding/{slug}/login-logo.webp    (categoria A, futuro)
 *     shared/icons/social/facebook.webp  (categoria C)
 *     shared/icons/social/instagram.webp (categoria C)
 *
 * Los archivos que no matcheen estos paths se ignoran silenciosamente
 * (ej. assets/frontend/amico/auth/*.svg son categoria B y NO
 * se suben al FTP — se sirven desde frontend/public/assets/ directo).
 */
final class SyncAssetsFromPublicCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'assets:sync-from-public
        {--store= : Slug del store especifico a sincronizar (default: todos)}
        {--asset-base= : Path to repo assets/ (default: base_path(assets))}
        {--storage-disk=ftp : Storage disk name (ftp real / local mock para tests)}
        {--dry-run : Solo reporta lo que haria, sin escribir en el FTP}
        {--force : Sobrescribe archivos existentes en el FTP}';

    /**
     * @var string
     */
    protected $description = 'Migra assets de marca commiteados en assets/ al FTP bajo la convencion tenants/{slug}/branding/ y shared/';

    /**
     * Directorio base de los assets de marca (relativo a la raiz del repo).
     */
    private const BRAND_ASSETS_BASE = 'assets';

    /**
     * Mapping de paths fuente (relativos a assets/) a paths destino
     * (relativos a FTP_ROOT).
     *
     * Categoria A — per-tenant, usa placeholder {slug} en el destino.
     * Categoria C — compartido, sin placeholder.
     *
     * @var array<string, string>
     */
    private const ASSET_MAPPINGS = [
        // Categoria A — per-tenant
        'branding/{slug}/logo.webp' => 'tenants/{slug}/branding/logo.webp',
        'branding/{slug}/favicon.webp' => 'tenants/{slug}/branding/favicon.webp',
        'branding/{slug}/og-image.webp' => 'tenants/{slug}/branding/og-image.webp',
        'branding/{slug}/cover.webp' => 'tenants/{slug}/branding/cover.webp',
        'branding/{slug}/login-logo.webp' => 'tenants/{slug}/branding/login-logo.webp',

        // Categoria C — compartido
        'shared/icons/social/facebook.webp' => 'shared/icons/social/facebook.webp',
        'shared/icons/social/instagram.webp' => 'shared/icons/social/instagram.webp',
    ];

    public function handle(StorageKeyDeriver $deriver): int
    {
        $storeFilter = $this->option(key: 'store');
        $assetBase = $this->option(key: 'asset-base') ?: base_path(path: self::BRAND_ASSETS_BASE);
        $storageDisk = $this->option(key: 'storage-disk') ?: 'ftp';
        $isDryRun = (bool) $this->option(key: 'dry-run');
        $isForce = (bool) $this->option(key: 'force');

        if ($isDryRun) {
            $this->warn(string: 'DRY-RUN mode: no se escribira nada en el FTP.');
        }

        $disk = Storage::disk(name: $storageDisk);
        $basePath = $assetBase;

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
        foreach ($this->detectStores(basePath: $basePath.'/branding', storeFilter: $storeFilter) as $storeSlug) {
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

        // Procesar categoria D (products per-tenant). Hashea con
        // StorageKeyDeriver y sube a tenants/{slug}/products/{slug}/{hash}.webp.
        // Solo si no hay filtro de store (un store no deberia tener sus
        // imagenes en estado inconsistente vs. las de otros stores).
        if ($storeFilter === null) {
            $this->syncProducts(
                disk: $disk,
                basePath: $basePath,
                deriver: $deriver,
                isDryRun: $isDryRun,
                isForce: $isForce,
                stats: $stats,
            );
        }

        $this->newLine();
        $this->info(string: "Resumen: {$stats['uploaded']} subidos, {$stats['skipped']} saltados, {$stats['errors']} errores.");

        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Sincroniza los webps de assets/products/{tenant}/{brand}/.../*.webp
     * al FTP, hasheando el storage_key con StorageKeyDeriver (mismo
     * algoritmo que usa SeedCatalogAssetsCommand para que el hash sea
     * reproducible).
     *
     * Estructura del repo:
     *   assets/products/{tenant-slug}/{brand-slug}/{product-slug}/
     *     {variant-slug}/{role}-{WxH}.webp
     *
     * Destino en FTP:
     *   tenants/{tenant-slug}/products/{product-slug}/{hash12}.webp
     *
     * ⚠️ La imagen se hashea por su CONTENIDO, no por el filename.
     *    Si el webp cambia (re-compresion), el hash cambia → el archivo
     *    viejo en el FTP queda huerfano. NO regenerar webps sin antes
     *    limpiar el FTP.
     *
     * @param  array{uploaded: int, skipped: int, errors: int}  $stats
     */
    private function syncProducts(
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        string $basePath,
        StorageKeyDeriver $deriver,
        bool $isDryRun,
        bool $isForce,
        array &$stats,
    ): void {
        $productBase = $basePath.'/products';
        if (! is_dir(filename: $productBase)) {
            return;
        }

        $this->line('Products:');
        // PHP glob('**/*.webp') NO es recursivo (sin GLOB_BRACE). Usamos
        // RecursiveDirectoryIterator para garantizar recursividad.
        $webps = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $productBase,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
            )
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'webp') {
                $webps[] = $file->getPathname();
            }
        }
        $processed = 0;

        foreach ($webps as $webpPath) {
            $relPath = substr(string: $webpPath, offset: strlen(string: $productBase) + 1);
            $parts = explode('/', $relPath);
            // Esperado: {tenant}/{brand}/{product}/{variant}/{file}.webp
            if (count($parts) !== 5) {
                continue;
            }
            [$tenantSlug, $brandSlug, $productSlug, $variantSlug, $filename] = $parts;

            $bytes = @file_get_contents(filename: $webpPath);
            if ($bytes === false) {
                $this->error("  - No se pudo leer {$webpPath}");
                $stats['errors']++;
                continue;
            }

            $storageKey = $deriver->derive(
                tenantSlug: $tenantSlug,
                collection: 'products',
                entity: $productSlug,
                content: $bytes,
                extension: 'webp',
            );

            if ($isDryRun) {
                $this->line("  - DRY-RUN: subiria {$relPath} -> {$storageKey}");
                $stats['uploaded']++;
                continue;
            }

            try {
                $existsAlready = $disk->exists(path: $storageKey);
            } catch (Throwable) {
                $existsAlready = false;
            }

            if ($existsAlready && ! $isForce) {
                $this->line("  - {$storageKey} ya existe (usar --force para sobrescribir)");
                $stats['skipped']++;
                continue;
            }

            try {
                $disk->put(path: $storageKey, contents: $bytes);
                $this->info("  - Subido: {$storageKey} (".strlen(string: $bytes).' bytes)');
                $stats['uploaded']++;
                $processed++;
            } catch (Throwable $e) {
                $this->error("  - Error subiendo {$storageKey}: {$e->getMessage()}");
                $stats['errors']++;
            }
        }
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

        if ($isDryRun) {
            $this->line(string: "  - DRY-RUN: subiria {$sourcePath} -> {$destPath}");
            $stats['uploaded']++;

            return;
        }

        // Crear el directorio padre recursivamente. El driver FTP de
        // Flysystem NO crea directorios padre automaticamente al hacer
        // put(), asi que si la jerarquia tenants/kitsune/branding/
        // no existe, el upload falla con 'Unable to resolve connection root'.
        if (! $isDryRun) {
            $parentDir = dirname(path: $destPath);
            if ($parentDir !== '.' && $parentDir !== '/') {
                // Crear recursivamente. makeDirectory solo crea 1 nivel,
                // asi que iteramos desde el nivel mas bajo hasta el root.
                // Usamos try/catch en exists() porque si el root no existe
                // (FTP vacio), exists() lanza una excepcion en vez de devolver false.
                $partsToCreate = [];
                $current = $parentDir;
                while ($current !== '.' && $current !== '/') {
                    $parentExists = false;
                    try {
                        $parentExists = $disk->exists(path: $current);
                    } catch (Throwable) {
                        // Si el directorio no existe (FTP vacio o path invalido),
                        // asumimos false y seguimos creando hacia arriba.
                        $parentExists = false;
                    }
                    if ($parentExists) {
                        break;
                    }
                    $partsToCreate[] = $current;
                    $current = dirname(path: $current);
                }
                // Crear desde el nivel mas alto al mas bajo.
                foreach (array_reverse(array: $partsToCreate) as $dirToCreate) {
                    try {
                        $disk->makeDirectory(path: $dirToCreate);
                    } catch (Throwable) {
                        // Si makeDirectory falla (ej. ya existe), ignorar.
                    }
                }
            }
        }

        // Verificar si el archivo ya existe. Try/catch porque exists()
        // puede lanzar excepcion si el directorio padre no existe.
        $destExists = false;
        try {
            $destExists = $disk->exists(path: $destPath);
        } catch (Throwable) {
            $destExists = false;
        }

        if ($destExists && ! $isForce) {
            $this->line(string: "  - {$destPath} ya existe (usar --force para sobrescribir)");
            $stats['skipped']++;

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
     * Detectar stores desde los subdirectorios de assets/branding/.
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
                $this->error(string: "Store '{$storeFilter}' no encontrado en assets/branding/");
            }
        }

        return array_values(array: $stores);
    }
}
