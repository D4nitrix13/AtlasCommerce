<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductVariant;
use App\Services\Assets\CatalogAssetsSeeder;
use App\Services\Assets\StorageKeyDeriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * ⚠️ IMPORTANTE — Lee antes de usar
 * =====================================
 *
 * Este command ASUME que las imágenes en `assets/products/{tenant}/.../*.webp`
 * ya están convertidas a formato WebP multi-size. NO aplica el algoritmo
 * de compresión/conversión a WebP — esa conversión ya se ejecutó en
 * staging vía `scripts/migrate-gibson-images.sh` antes del push al repo.
 *
 * Si necesitás regenerar WebPs desde PNG source, NO uses este command.
 * Usá `scripts/migrate-gibson-images.sh <cat> <brand>` primero.
 *
 * QUÉ HACE ESTE COMMAND
 * ----------------------
 * 1. Discover: filesystem → (brand, product, variant, role, size)
 * 2. Hash:    binario → storage_key vía StorageKeyDeriver (SHA-256 prefix 12,
 *             irreversible — BD queda con path definitivo y reproducible)
 * 3. Upload:  Storage::put() al disk configurado (local-fake o ftp real)
 * 4. BD:      products, product_variants, product_stores, product_images
 * 5. Verify:  BD rows == archivos en storage == hashes match
 *
 * Stores en BD tienen:
 *   is_primary  =  true para role='front'   (imagen default del PDP)
 *                  false para los demás
 *   sort_order  =  posición en galería (1=front, 2=back, 3=body, ... — ver
 *                  CatalogAssetsSeeder::ROLE_ORDER para el orden completo)
 *
 * Convención del frontend: hover sobre la imagen del PDP alterna entre
 * is_primary y la siguiente imagen con sort_order=2 (típicamente back).
 *
 * USO
 * ---
 *   php artisan assets:seed-catalog                              # default
 *   php artisan assets:seed-catalog --dry-run                   # solo report
 *   php artisan assets:seed-catalog --storage-disk=local        # mock
 *   php artisan assets:seed-catalog --storage-disk=ftp          # real FTP
 *   php artisan assets:seed-catalog --tenant=instrumentos-musicales-la-voz
 *   php artisan assets:seed-catalog --verify-only               # solo Phase 5
 */
final class SeedCatalogAssetsCommand extends Command
{
    protected $signature = 'assets:seed-catalog
        {--tenant=instrumentos-musicales-la-voz : Tenant slug}
        {--asset-base= : Path to repo assets/ (default: env ATLAS_ASSET_BASE or default)}
        {--storage-disk=local : Storage disk name (local / ftp)}
        {--dry-run : Report without writing}
        {--verify-only : Run only Phase 5 verification (no upload or DB insert)}';

    protected $description = 'Pobla BD + storage desde assets/products/{tenant}/ (ASUME WebPs ya convertidos).';

    public function handle(StorageKeyDeriver $deriver): int
    {
        $assetBase = $this->option('asset-base')
            ?: env('ATLAS_ASSET_BASE', base_path('../../assets'));
        $tenant = $this->option('tenant');
        $disk = $this->option('storage-disk');
        $dryRun = (bool) $this->option('dry-run');
        $verifyOnly = (bool) $this->option('verify-only');

        $this->info("=== SeedCatalogAssets ===");
        $this->line("Asset base:     {$assetBase}/products/{$tenant}/");
        $this->line("Storage disk:   {$disk}");
        $this->line("Mode:           " . ($dryRun ? 'DRY-RUN' : ($verifyOnly ? 'VERIFY-ONLY' : 'WRITE')));
        $this->line("");

        // Validar que el asset base existe
        $tenantPath = rtrim($assetBase, '/') . '/products/' . $tenant;
        if (! is_dir($tenantPath)) {
            $this->error("❌ No existe: {$tenantPath}");
            return self::FAILURE;
        }

        $seeder = new CatalogAssetsSeeder(
            assetBase: $assetBase,
            tenantSlug: $tenant,
            storageDisk: $disk,
            deriver: $deriver,
        );

        // VERIFY ONLY
        if ($verifyOnly) {
            $this->info('Running Phase 5 (verify-only)...');
            return $this->runVerify($seeder);
        }

        // SEED (write or dry-run)
        $stats = $seeder->seed();

        $this->info('Phase 1-4 complete.');
        $this->line("  Products created/updated:  {$stats['products_created']}");
        $this->line("  Variants created/updated:  {$stats['variants_created']}");
        $this->line("  Images uploaded:           {$stats['images_uploaded']}");
        $this->line("  Images skipped (already on storage): {$stats['images_skipped']}");
        if ($stats['warnings'] !== []) {
            $this->warn('Warnings:');
            foreach ($stats['warnings'] as $w) {
                $this->line("  ⚠️  {$w}");
            }
        }
        if ($stats['errors'] !== []) {
            $this->error('Errors:');
            foreach ($stats['errors'] as $e) {
                $this->line("  ❌ {$e}");
            }
        }
        $this->line('');

        // PHASE 5 (verify)
        $this->info('Running Phase 5 (verify end-to-end)...');
        return $this->runVerify($seeder);
    }

    private function runVerify(CatalogAssetsSeeder $seeder): int
    {
        $result = $seeder->verify();
        $this->line('=== Phase 5: Verify results ===');
        foreach ($result['stats'] as $k => $v) {
            $this->line("  {$k}: {$v}");
        }
        if ($result['issues'] === []) {
            $this->info('✅ All products verified.');
            return self::SUCCESS;
        }
        $this->error('❌ Issues found:');
        foreach ($result['issues'] as $i) {
            $this->line("  - {$i}");
        }
        return self::FAILURE;
    }
}
