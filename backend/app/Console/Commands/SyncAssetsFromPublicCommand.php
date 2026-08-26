<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Sincroniza los assets commiteados en backend/public/images/stores/
 * al FTP, usando la convencion nueva de paths por tenant.
 *
 * ANTES (commiteado en git):
 *     backend/public/images/stores/{slug}/brand/logo.{ext}
 *
 * DESPUES (en FTP, path relativo a FTP_ROOT):
 *     tenants/{slug}/branding/logo.{ext}
 *
 * Esto es la migracion inicial del refactor "tenant logos to FTP
 * storage". Despues de correr este comando UNA vez, los archivos
 * commiteados se pueden borrar del repo con `git rm` (siguiente
 * commit del refactor).
 *
 * Uso:
 *     php artisan assets:sync-from-public                    # sincroniza todos los stores
 *     php artisan assets:sync-from-public --store=panda-estampados   # solo uno
 *     php artisan assets:sync-from-public --dry-run          # no escribe, solo reporta
 *
 * El comando detecta los stores buscando directorios bajo
 * `backend/public/images/stores/`. Si un store no tiene un logo
 * commiteado, se reporta como warning y se skipea.
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
    protected $description = 'Migra logos commiteados en public/ al FTP bajo la nueva convencion tenants/{slug}/branding/';

    /**
     * Directorio base donde estan los assets commiteados.
     */
    private const PUBLIC_ASSETS_BASE = 'images/stores';

    /**
     * Subdirectorios dentro de PUBLIC_ASSETS_BASE que se sincronizan.
     * key = nombre del subdir en public/, value = nombre en el FTP.
     */
    private const ASSET_MAPPINGS = [
        'brand/logo.webp' => 'branding/logo.webp',
        'brand/logo.png' => 'branding/logo.png',
        'brand/logo.svg' => 'branding/logo.svg',
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
        $publicPath = public_path(path: self::PUBLIC_ASSETS_BASE);

        if (! is_dir(filename: $publicPath)) {
            $this->error(string: 'Directorio public/{self::PUBLIC_ASSETS_BASE} no existe.');

            return self::FAILURE;
        }

        // Detectar stores a sincronizar.
        $stores = $this->detectStores(publicPath: $publicPath, storeFilter: $storeFilter);

        if ($stores === []) {
            $this->warn(string: 'No se encontraron stores para sincronizar.');

            return self::SUCCESS;
        }

        $uploaded = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($stores as $storeSlug) {
            $this->line(string: "Store: {$storeSlug}");

            foreach (self::ASSET_MAPPINGS as $publicSubpath => $ftpSubpath) {
                $sourcePath = self::PUBLIC_ASSETS_BASE.'/'.$storeSlug.'/'.$publicSubpath;
                $ftpPath = 'tenants/'.$storeSlug.'/'.$ftpSubpath;

                $sourceFullPath = public_path(path: $sourcePath);

                if (! file_exists(filename: $sourceFullPath)) {
                    // No es un error — un store puede no tener todos los formatos.
                    continue;
                }

                if ($disk->exists(path: $ftpPath) && ! $isForce) {
                    $this->line(string: "  - {$ftpPath} ya existe (usar --force para sobrescribir)");
                    $skipped++;

                    continue;
                }

                if ($isDryRun) {
                    $this->line(string: "  - DRY-RUN: subiria {$sourcePath} -> {$ftpPath}");
                    $uploaded++;

                    continue;
                }

                try {
                    $contents = file_get_contents(filename: $sourceFullPath);

                    if ($contents === false) {
                        $this->error(string: "  - No se pudo leer {$sourceFullPath}");
                        $errors++;

                        continue;
                    }

                    $disk->put(path: $ftpPath, contents: $contents);
                    $this->info(string: "  - Subido: {$ftpPath} (".strlen(string: $contents).' bytes)');
                    $uploaded++;
                } catch (Throwable $e) {
                    $this->error(string: "  - Error subiendo {$ftpPath}: {$e->getMessage()}");
                    $errors++;
                }
            }
        }

        $this->newLine();
        $this->info(string: "Resumen: {$uploaded} subidos, {$skipped} saltados, {$errors} errores.");

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Detectar stores desde los subdirectorios de public/images/stores/.
     *
     * @return array<int, string>
     */
    private function detectStores(string $publicPath, ?string $storeFilter): array
    {
        $all = array_values(array: scandir(directory: $publicPath) ?: []);

        $stores = array_filter(
            array: $all,
            callback: fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir(filename: $publicPath.'/'.$entry),
        );

        if ($storeFilter !== null) {
            $stores = array_filter(
                array: $stores,
                callback: fn (string $slug): bool => $slug === $storeFilter,
            );

            if ($stores === []) {
                $this->error(string: "Store '{$storeFilter}' no encontrado en public/{self::PUBLIC_ASSETS_BASE}/");
            }
        }

        return array_values(array: $stores);
    }
}
