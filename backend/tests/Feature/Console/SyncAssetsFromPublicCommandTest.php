<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests para SyncAssetsFromPublicCommand extendido con soporte
 * para products/ (categoria D) hasheado via StorageKeyDeriver.
 */
final class SyncAssetsFromPublicCommandTest extends TestCase
{
    private string $tempAssetBase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('ftp');

        // Estructura de test: assets/{tenant}/{brand}/{product}/{variant}/{role}-{WxH}.webp
        $this->tempAssetBase = sys_get_temp_dir() . '/atlas-sync-test-' . uniqid();
        mkdir($this->tempAssetBase . '/branding/la-voz', 0755, true);
        mkdir($this->tempAssetBase . '/shared/icons/social', 0755, true);
        mkdir($this->tempAssetBase . '/products/la-voz/gibson/sg-standard/heritage-cherry', 0755, true);
        file_put_contents($this->tempAssetBase . '/branding/la-voz/logo.webp', 'logo-bytes');
        file_put_contents($this->tempAssetBase . '/shared/icons/social/facebook.webp', 'fb-bytes');
        file_put_contents($this->tempAssetBase . '/products/la-voz/gibson/sg-standard/heritage-cherry/front-1600x1600.webp', 'webp-1');
        file_put_contents($this->tempAssetBase . '/products/la-voz/gibson/sg-standard/heritage-cherry/back-1600x1600.webp', 'webp-2');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempAssetBase)) {
            exec(sprintf('rm -rf %s', escapeshellarg($this->tempAssetBase)));
        }
        parent::tearDown();
    }

    public function test_dry_run_does_not_upload_to_storage(): void
    {
        $exitCode = $this->artisan('assets:sync-from-public', [
            '--asset-base' => $this->tempAssetBase,
            '--storage-disk' => 'ftp',
            '--dry-run' => true,
        ])->run();

        $this->assertSame(0, $exitCode);
        // FTP storage debería estar VACÍO
        Storage::disk('ftp')->assertDirectoryEmpty('/');
    }

    public function test_uploads_branding_with_legible_name(): void
    {
        $exitCode = $this->artisan('assets:sync-from-public', [
            '--asset-base' => $this->tempAssetBase,
            '--storage-disk' => 'ftp',
        ])->run();

        $this->assertSame(0, $exitCode);
        // Branding usa nombre LEGIBLE (no hash): tenants/la-voz/branding/logo.webp
        Storage::disk('ftp')->assertExists('tenants/la-voz/branding/logo.webp');
    }

    public function test_uploads_products_with_hashed_name(): void
    {
        $exitCode = $this->artisan('assets:sync-from-public', [
            '--asset-base' => $this->tempAssetBase,
            '--storage-disk' => 'ftp',
        ])->run();

        $this->assertSame(0, $exitCode);
        // Products usa nombre HASHEADO: tenants/la-voz/products/sg-standard/{hash12}.webp
        // El hash es del contenido del webp, no del filename.
        $files = Storage::disk('ftp')->allFiles('tenants/la-voz/products');
        $this->assertGreaterThan(0, count($files));
        // Verifica que NO existe el nombre legible
        $hasLegibleName = false;
        foreach ($files as $f) {
            if (str_contains($f, 'front-1600x1600')) {
                $hasLegibleName = true;
                break;
            }
        }
        $this->assertFalse($hasLegibleName, 'Products NO debe usar nombre legible en el FTP');
    }

    public function test_skips_existing_files_without_force(): void
    {
        // Primer sync: sube 2
        $this->artisan('assets:sync-from-public', [
            '--asset-base' => $this->tempAssetBase,
            '--storage-disk' => 'ftp',
        ])->run();

        $beforeCount = count(Storage::disk('ftp')->allFiles('tenants/la-voz/products'));

        // Segundo sync: debería skip todo (ya está subido)
        $this->artisan('assets:sync-from-public', [
            '--asset-base' => $this->tempAssetBase,
            '--storage-disk' => 'ftp',
        ])->run();

        $afterCount = count(Storage::disk('ftp')->allFiles('tenants/la-voz/products'));
        $this->assertSame($beforeCount, $afterCount, 'Segundo sync sin --force no debe cambiar nada');
    }
}
