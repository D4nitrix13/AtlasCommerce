<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests para SeedCatalogAssetsCommand.
 *
 * ⚠️ ASUME que las imágenes ya están en WebP en `assets/` — no prueba
 *    la pipeline de conversión PNG → WebP.
 */
final class SeedCatalogAssetsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $tempAssetBase;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase trunca todas las tablas. Re-crear la currency que
        // el Store referenciará vía FK (FK violation si no existe).
        $currency = Currency::query()->updateOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true, 'is_primary' => true],
        );

        // Setup: crear tenant store, brand, category, currency basics
        Store::query()->updateOrCreate(
            ['slug' => 'instrumentos-musicales-la-voz'],
            ['name' => 'La Voz Instrumentos', 'is_active' => true, 'currency_id' => $currency->id],
        );

        // Storage::fake para no tocar filesystem real ni FTP
        Storage::fake('local-fake');

        // Crear estructura filesystem temporal con 1 producto (1 variant, 2 roles)
        $this->tempAssetBase = sys_get_temp_dir() . '/atlas-test-' . uniqid();
        mkdir($this->tempAssetBase . '/products/instrumentos-musicales-la-voz/gibson/test-guitar/vintage-sunburst', 0755, true);
        file_put_contents(
            $this->tempAssetBase . '/products/instrumentos-musicales-la-voz/gibson/test-guitar/vintage-sunburst/front-1600x1600.webp',
            $this->fakeWebpBytes()
        );
        file_put_contents(
            $this->tempAssetBase . '/products/instrumentos-musicales-la-voz/gibson/test-guitar/vintage-sunburst/back-1600x1600.webp',
            $this->fakeWebpBytes()
        );
    }

    protected function tearDown(): void
    {
        // Limpiar filesystem temporal
        if (is_dir($this->tempAssetBase)) {
            exec(sprintf('rm -rf %s', escapeshellarg($this->tempAssetBase)));
        }
        parent::tearDown();
    }

    public function test_command_discovers_creates_and_uploads_for_one_product(): void
    {
        $exitCode = $this->artisan('assets:seed-catalog', [
            '--asset-base' => $this->tempAssetBase,
            '--storage-disk' => 'local-fake',
        ])->run();

        $this->assertSame(0, $exitCode, 'Command should exit 0');

        // Producto creado en BD
        $product = Product::query()->where('slug', 'test-guitar')->first();
        $this->assertNotNull($product);
        $this->assertSame('gibson', $product->brand->slug);

        // Variant creado
        $variant = $product->variants()->first();
        $this->assertNotNull($variant);
        $this->assertSame('vintage-sunburst', $variant->finish_slug);

        // Imágenes: 2 imágenes (front + back)
        $images = $product->images()->orderBy('sort_order')->get();
        $this->assertCount(2, $images);

        // Front es primary, sort_order=1
        $front = $images->where('is_primary', true)->first();
        $this->assertNotNull($front);
        $this->assertSame(1, (int) $front->sort_order);
        $this->assertStringStartsWith('tenants/instrumentos-musicales-la-voz/products/test-guitar/', $front->image_url);
        $this->assertStringEndsWith('.webp', $front->image_url);

        // Back no es primary, sort_order=2
        $back = $images->where('is_primary', false)->first();
        $this->assertNotNull($back);
        $this->assertSame(2, (int) $back->sort_order);

        // Verificar que las imágenes existen en Storage::fake
        Storage::disk('local-fake')->assertExists($front->image_url);
        Storage::disk('local-fake')->assertExists($back->image_url);

        // Verificar que product_stores vincula con el tenant
        $store = Store::query()->where('slug', 'instrumentos-musicales-la-voz')->firstOrFail();
        $this->assertDatabaseHas('product_stores', [
            'product_id' => $product->id,
            'store_id' => $store->id,
        ]);
    }

    public function test_dry_run_does_not_create_db_rows(): void
    {
        $exitCode = $this->artisan('assets:seed-catalog', [
            '--asset-base' => $this->tempAssetBase,
            '--storage-disk' => 'local-fake',
            '--dry-run' => true,
        ])->run();

        // Pero el assert de arriba debería pasar porque el discover+seed ya ocurrió arriba
        // Limpiamos y rerun con dry-run
        Product::query()->delete();
        ProductImage::query()->delete();
        ProductVariant::query()->delete();

        $exitCode = $this->artisan('assets:seed-catalog', [
            '--asset-base' => $this->tempAssetBase,
            '--storage-disk' => 'local-fake',
            '--dry-run' => true,
        ])->run();

        $this->assertSame(0, $exitCode);
        // Con dry-run no debe crear filas BD
        $this->assertSame(0, Product::query()->count());
    }

    public function test_verify_only_succeeds_after_seed(): void
    {
        $this->artisan('assets:seed-catalog', [
            '--asset-base' => $this->tempAssetBase,
            '--storage-disk' => 'local-fake',
        ])->run();

        // Re-ejecutar solo verify (sin re-seed)
        $exitCode = $this->artisan('assets:seed-catalog', [
            '--asset-base' => $this->tempAssetBase,
            '--storage-disk' => 'local-fake',
            '--verify-only' => true,
        ])->run();

        $this->assertSame(0, $exitCode);
    }

    public function test_handles_missing_asset_base_gracefully(): void
    {
        $exitCode = $this->artisan('assets:seed-catalog', [
            '--asset-base' => '/tmp/nonexistent-asset-base-xyz',
            '--storage-disk' => 'local-fake',
        ])->run();

        $this->assertSame(1, $exitCode, 'Should fail with exit 1 when path missing');
    }

    /**
     * Genera bytes de un "webp" mínimo válido (RIFF magic + VP8 chunk).
     * StorageKeyDeriver los hashea igual — solo necesitamos bytes consistentes.
     */
    private function fakeWebpBytes(): string
    {
        return 'RIFF' . random_bytes(4) . // size
            'WEBP' . 'VP8 ' . random_bytes(20);
    }
}
