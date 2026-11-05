<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Assets\AssetFilenameParser;
use App\Services\Assets\CatalogProductImageSeeder;
use App\Services\Assets\StorageKeyDeriver;
use Illuminate\Console\Command;

/**
 * Sube las imágenes WebP multi-size del catálogo Panda/Kitsune al
 * storage configurado (local / ftp) y crea las filas de
 * `product_images`.
 *
 * ⚠️ ASUME que los WebPs en `assets/products/{tenant}/...` ya están
 * convertidos (mismo contrato que `assets:seed-catalog`). No convierte
 * ni comprime: hashea el binario, sube si no está e inserta la fila.
 *
 * A diferencia de `assets:seed-catalog`, NO crea productos ni
 * variantes: los matchea por `Product.slug` +
 * `ProductVariant.finish_slug` y lo que no encuentra lo avisa.
 *
 * USO
 * ---
 *   php artisan assets:seed-images                                   # panda-estampados + kitsune
 *   php artisan assets:seed-images --tenant=panda-estampados         # un tenant
 *   php artisan assets:seed-images --dry-run                         # solo reporta
 *   php artisan assets:seed-images --storage-disk=ftp                # FTP real
 */
final class SeedProductImagesCommand extends Command
{
    /**
     * Tenants procesados cuando no se pasa `--tenant`.
     */
    private const DEFAULT_TENANTS = ['panda-estampados', 'kitsune'];

    protected $signature = 'assets:seed-images
        {--tenant= : Tenant slug (default: panda-estampados y kitsune)}
        {--asset-base= : Path to repo assets/ (default: env ATLAS_ASSET_BASE or default)}
        {--storage-disk=local : Storage disk name (local / ftp)}
        {--dry-run : Report without writing}';

    protected $description = 'Sube las imágenes del catálogo Panda/Kitsune al storage (FTP) y crea las filas de product_images.';

    public function handle(StorageKeyDeriver $deriver): int
    {
        $assetBase = $this->option('asset-base')
            ?: env('ATLAS_ASSET_BASE', base_path('../../assets'));
        $disk = $this->option('storage-disk');
        $dryRun = (bool) $this->option('dry-run');
        $tenantOption = $this->option('tenant');
        $tenants = (is_string($tenantOption) && $tenantOption !== '')
            ? [$tenantOption]
            : self::DEFAULT_TENANTS;

        $this->info('=== SeedProductImages ===');
        $this->line("Asset base:     {$assetBase}");
        $this->line("Storage disk:   {$disk}");
        $this->line('Mode:           '.($dryRun ? 'DRY-RUN' : 'WRITE'));
        $this->line('Tenants:        '.implode(separator: ', ', array: $tenants));
        $this->line('');

        $totals = [
            'images_uploaded' => 0,
            'images_skipped' => 0,
            'images_created' => 0,
            'errors' => [],
            'warnings' => [],
        ];
        $exitCode = self::SUCCESS;

        foreach ($tenants as $tenant) {
            $tenantPath = rtrim($assetBase, '/').'/products/'.$tenant;
            if (! is_dir($tenantPath)) {
                $this->error("❌ No existe: {$tenantPath}");
                $exitCode = self::FAILURE;

                continue;
            }

            $seeder = new CatalogProductImageSeeder(
                assetBase: $assetBase,
                tenantSlug: $tenant,
                storageDisk: $disk,
                deriver: $deriver,
                filenames: new AssetFilenameParser,
                dryRun: $dryRun,
            );

            $stats = $seeder->seed();

            $this->info("Tenant {$tenant}:");
            $this->printStats($stats);

            $totals['images_uploaded'] += $stats['images_uploaded'];
            $totals['images_skipped'] += $stats['images_skipped'];
            $totals['images_created'] += $stats['images_created'];
            $totals['errors'] = [...$totals['errors'], ...$stats['errors']];
            $totals['warnings'] = [...$totals['warnings'], ...$stats['warnings']];

            if ($stats['errors'] !== []) {
                $exitCode = self::FAILURE;
            }
        }

        $this->info('=== Total ===');
        $this->printStats($totals);

        return $exitCode;
    }

    /**
     * @param  array{
     *   images_uploaded: int, images_skipped: int, images_created: int,
     *   errors: array<int, string>, warnings: array<int, string>,
     * }  $stats
     */
    private function printStats(array $stats): void
    {
        $this->line("  Images uploaded:           {$stats['images_uploaded']}");
        $this->line("  Images skipped (already on storage): {$stats['images_skipped']}");
        $this->line("  Image rows created:        {$stats['images_created']}");
        if ($stats['warnings'] !== []) {
            $this->warn('Warnings:');
            foreach ($stats['warnings'] as $warning) {
                $this->line("  ⚠️  {$warning}");
            }
        }
        if ($stats['errors'] !== []) {
            $this->error('Errors:');
            foreach ($stats['errors'] as $error) {
                $this->line("  ❌ {$error}");
            }
        }
    }
}
