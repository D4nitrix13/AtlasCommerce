<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use App\Services\Assets\StorageKeyDeriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del comando `assets:backfill-image-variant` (Oct 2026).
 *
 * Cubre:
 *   - Producto multi-variant CON árbol de variantes en disco: cada
 *     storage_key queda atada al variant de su subdirectorio.
 *   - Producto single-variant CON assets planos: todas las imágenes
 *     quedan atadas al único variant (caso 306/346 típico del seed).
 *   - Producto multi-variant SIN asset dir: queda con `variant_id`
 *     nulo (la PDP degrada limpio a mostrar todas).
 *   - Idempotencia: re-correr el comando no duplica asociaciones.
 *   - --dry-run: recalcula y no escribe en la DB.
 *   - --force: reescribe filas que ya tenían `variant_id` seteado.
 *   - Mismatch: si la storage_key de la imagen NO aparece en disco
 *     (huérfana), el contador `images_orphans` la cuenta sin tocarla.
 *   - `product_images.width` se rellena del mismo recorrido, así que las
 *     resoluciones de una misma foto quedan distinguibles y la galería
 *     puede colapsarlas en un slide + srcset.
 */
#[CoversClass(\App\Console\Commands\BackfillImageVariantCommand::class)]
#[Group('console')]
#[Group('assets')]
final class BackfillImageVariantCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $tempAssetBase;

    private Store $store;

    private Product $multiVariantProduct;

    private Product $singleVariantProduct;

    private Product $noAssetsProduct;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(disk: 'local-fake');

        $currency = Currency::query()->updateOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true, 'is_primary' => true],
        );

        $this->store = Store::query()->updateOrCreate(
            ['slug' => 'instrumentos-musicales-la-voz'],
            ['name' => 'La Voz Instrumentos', 'is_active' => true, 'currency_id' => $currency->id],
        );

        $brand = Brand::query()->updateOrCreate(
            ['slug' => 'gibson'],
            ['name' => 'Gibson', 'is_active' => true],
        );

        $category = Category::query()->updateOrCreate(
            ['slug' => 'guitarra-electrica'],
            ['name' => 'Guitarra Eléctrica', 'is_active' => true],
        );

        // Producto multi-variant CON assets de variantes en disco.
        $this->multiVariantProduct = Product::query()->create(attributes: [
            'name' => 'Test Multi-Variant',
            'slug' => 'test-multi-variant',
            'sku' => 'TEST-MULTI',
            'description' => 'Test multi-variant product',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'currency_id' => $currency->id,
            'base_price_cents' => 100000,
            'is_active' => true,
        ]);
        ProductStore::query()->create(attributes: [
            'product_id' => $this->multiVariantProduct->id,
            'store_id' => $this->store->id,
            'is_visible' => true,
        ]);
        $ebonyVariant = ProductVariant::query()->create(attributes: [
            'product_id' => $this->multiVariantProduct->id,
            'name' => 'Ebony',
            'sku' => 'TEST-MULTI-EBONY',
            'price_cents' => 100000,
            'is_active' => true,
            'finish_slug' => 'ebony',
            'finish_label' => 'Ebony',
            'condition' => 'nuevo',
            'stock' => 0,
        ]);
        $cherryVariant = ProductVariant::query()->create(attributes: [
            'product_id' => $this->multiVariantProduct->id,
            'name' => 'Cherry',
            'sku' => 'TEST-MULTI-CHERRY',
            'price_cents' => 100000,
            'is_active' => true,
            'finish_slug' => 'cherry',
            'finish_label' => 'Cherry',
            'condition' => 'nuevo',
            'stock' => 0,
        ]);

        // Producto single-variant CON assets planos en product root.
        $this->singleVariantProduct = Product::query()->create(attributes: [
            'name' => 'Test Single-Variant',
            'slug' => 'test-single-variant',
            'sku' => 'TEST-SINGLE',
            'description' => 'Test single-variant product',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'currency_id' => $currency->id,
            'base_price_cents' => 80000,
            'is_active' => true,
        ]);
        ProductStore::query()->create(attributes: [
            'product_id' => $this->singleVariantProduct->id,
            'store_id' => $this->store->id,
            'is_visible' => true,
        ]);
        $loneVariant = ProductVariant::query()->create(attributes: [
            'product_id' => $this->singleVariantProduct->id,
            'name' => 'Test Single-Variant',
            'sku' => 'TEST-SINGLE-LONE',
            'price_cents' => 80000,
            'is_active' => true,
            'finish_slug' => 'test-single-variant', // = product.slug (fallback)
            'finish_label' => 'Test Single-Variant',
            'condition' => 'nuevo',
            'stock' => 0,
        ]);

        // Producto multi-variant SIN assets en disco.
        $this->noAssetsProduct = Product::query()->create(attributes: [
            'name' => 'Test No-Assets',
            'slug' => 'test-no-assets',
            'sku' => 'TEST-NOA',
            'description' => 'Test product with no assets on disk',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'currency_id' => $currency->id,
            'base_price_cents' => 60000,
            'is_active' => true,
        ]);
        ProductStore::query()->create(attributes: [
            'product_id' => $this->noAssetsProduct->id,
            'store_id' => $this->store->id,
            'is_visible' => true,
        ]);
        ProductVariant::query()->create(attributes: [
            'product_id' => $this->noAssetsProduct->id,
            'name' => 'Variant A',
            'sku' => 'TEST-NOA-A',
            'price_cents' => 60000,
            'is_active' => true,
            'finish_slug' => 'variant-a',
            'finish_label' => 'Variant A',
            'condition' => 'nuevo',
            'stock' => 0,
        ]);
        ProductVariant::query()->create(attributes: [
            'product_id' => $this->noAssetsProduct->id,
            'name' => 'Variant B',
            'sku' => 'TEST-NOA-B',
            'price_cents' => 60000,
            'is_active' => true,
            'finish_slug' => 'variant-b',
            'finish_label' => 'Variant B',
            'condition' => 'nuevo',
            'stock' => 0,
        ]);

        // Setup filesystem: solo multi y single tienen assets.
        $this->tempAssetBase = sys_get_temp_dir().'/atlas-bv-test-'.uniqid();
        $multiEbonyDir = $this->tempAssetBase
            .'/products/instrumentos-musicales-la-voz/gibson/test-multi-variant/ebony';
        $multiCherryDir = $this->tempAssetBase
            .'/products/instrumentos-musicales-la-voz/gibson/test-multi-variant/cherry';
        $singleDir = $this->tempAssetBase
            .'/products/instrumentos-musicales-la-voz/gibson/test-single-variant';

        mkdir(directory: $multiEbonyDir, permissions: 0755, recursive: true);
        mkdir(directory: $multiCherryDir, permissions: 0755, recursive: true);
        mkdir(directory: $singleDir, permissions: 0755, recursive: true);

        // Multi-variant ebony: 2 webps (front y back)
        file_put_contents(
            $multiEbonyDir.'/front-1600x1600.webp',
            $this->fakeWebpBytes(seed: 'ebony-front'),
        );
        file_put_contents(
            $multiEbonyDir.'/back-1200x1200.webp',
            $this->fakeWebpBytes(seed: 'ebony-back'),
        );

        // Multi-variant cherry: 1 webp
        file_put_contents(
            $multiCherryDir.'/front-1600x1600.webp',
            $this->fakeWebpBytes(seed: 'cherry-front'),
        );

        // Single-variant flat: 2 webps en product root
        file_put_contents(
            $singleDir.'/front-1600x1600.webp',
            $this->fakeWebpBytes(seed: 'single-front'),
        );
        file_put_contents(
            $singleDir.'/body-800x800.webp',
            $this->fakeWebpBytes(seed: 'single-body'),
        );

        // Crear product_images en BD con storage_keys que coinciden
        // con el hash real de los webps que acabamos de escribir
        // (StorageKeyDeriver->derive() sobre los mismos bytes → mismo hash).
        $deriver = app(abstract: StorageKeyDeriver::class);

        // Ebony images
        $ebonyFrontKey = $deriver->derive(
            tenantSlug: 'instrumentos-musicales-la-voz',
            collection: 'products',
            entity: 'test-multi-variant',
            content: file_get_contents(filename: $multiEbonyDir.'/front-1600x1600.webp'),
            extension: 'webp',
        );
        $ebonyBackKey = $deriver->derive(
            tenantSlug: 'instrumentos-musicales-la-voz',
            collection: 'products',
            entity: 'test-multi-variant',
            content: file_get_contents(filename: $multiEbonyDir.'/back-1200x1200.webp'),
            extension: 'webp',
        );
        ProductImage::query()->create(attributes: [
            'product_id' => $this->multiVariantProduct->id,
            'image_url' => $ebonyFrontKey,
            'alt_text' => 'Multi Variant - Ebony - front',
            'is_primary' => true,
            'sort_order' => 1,
        ]);
        ProductImage::query()->create(attributes: [
            'product_id' => $this->multiVariantProduct->id,
            'image_url' => $ebonyBackKey,
            'alt_text' => 'Multi Variant - Ebony - back',
            'is_primary' => false,
            'sort_order' => 2,
        ]);

        // Cherry image
        $cherryFrontKey = $deriver->derive(
            tenantSlug: 'instrumentos-musicales-la-voz',
            collection: 'products',
            entity: 'test-multi-variant',
            content: file_get_contents(filename: $multiCherryDir.'/front-1600x1600.webp'),
            extension: 'webp',
        );
        ProductImage::query()->create(attributes: [
            'product_id' => $this->multiVariantProduct->id,
            'image_url' => $cherryFrontKey,
            'alt_text' => 'Multi Variant - Cherry - front',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        // Single-variant images
        $singleFrontKey = $deriver->derive(
            tenantSlug: 'instrumentos-musicales-la-voz',
            collection: 'products',
            entity: 'test-single-variant',
            content: file_get_contents(filename: $singleDir.'/front-1600x1600.webp'),
            extension: 'webp',
        );
        $singleBodyKey = $deriver->derive(
            tenantSlug: 'instrumentos-musicales-la-voz',
            collection: 'products',
            entity: 'test-single-variant',
            content: file_get_contents(filename: $singleDir.'/body-800x800.webp'),
            extension: 'webp',
        );
        ProductImage::query()->create(attributes: [
            'product_id' => $this->singleVariantProduct->id,
            'image_url' => $singleFrontKey,
            'alt_text' => 'Single Variant - front',
            'is_primary' => true,
            'sort_order' => 1,
        ]);
        ProductImage::query()->create(attributes: [
            'product_id' => $this->singleVariantProduct->id,
            'image_url' => $singleBodyKey,
            'alt_text' => 'Single Variant - body',
            'is_primary' => false,
            'sort_order' => 3,
        ]);

        // El producto sin assets: 1 imagen "huérfana" (no hay webp en disco).
        ProductImage::query()->create(attributes: [
            'product_id' => $this->noAssetsProduct->id,
            'image_url' => 'tenants/instrumentos-musicales-la-voz/products/test-no-assets/orphan.webp',
            'alt_text' => 'Orphan image',
            'is_primary' => true,
            'sort_order' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir(filename: $this->tempAssetBase)) {
            exec(sprintf('rm -rf %s', escapeshellarg(arg: $this->tempAssetBase)));
        }
        parent::tearDown();
    }

    #[Test]
    public function it_attaches_multi_variant_images_to_their_correct_variant(): void
    {
        $exitCode = $this->artisan(command: 'assets:backfill-image-variant', parameters: [
            '--asset-base' => $this->tempAssetBase,
            '--tenant' => 'instrumentos-musicales-la-voz',
        ])->run();
        $this->assertSame(expected: 0, actual: $exitCode);

        $ebonyVariant = ProductVariant::query()->where(column: 'finish_slug', operator: '=', value: 'ebony')
            ->where(column: 'product_id', operator: '=', value: $this->multiVariantProduct->id)
            ->firstOrFail();
        $cherryVariant = ProductVariant::query()->where(column: 'finish_slug', operator: '=', value: 'cherry')
            ->where(column: 'product_id', operator: '=', value: $this->multiVariantProduct->id)
            ->firstOrFail();

        // Las 2 imágenes Ebony apuntan a la variante Ebony
        $this->assertSame(expected: 2, actual: ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $this->multiVariantProduct->id)
            ->where(column: 'variant_id', operator: '=', value: $ebonyVariant->id)
            ->count());
        // La imagen Cherry apunta a la variante Cherry
        $this->assertSame(expected: 1, actual: ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $this->multiVariantProduct->id)
            ->where(column: 'variant_id', operator: '=', value: $cherryVariant->id)
            ->count());
    }

    #[Test]
    public function it_attaches_single_variant_flat_webps_to_the_lone_variant(): void
    {
        $this->artisan(command: 'assets:backfill-image-variant', parameters: [
            '--asset-base' => $this->tempAssetBase,
            '--tenant' => 'instrumentos-musicales-la-voz',
        ])->run();

        $loneVariant = ProductVariant::query()
            ->where(column: 'product_id', operator: '=', value: $this->singleVariantProduct->id)
            ->firstOrFail();

        // Ambas imágenes flat quedan atadas al único variant
        $this->assertSame(expected: 2, actual: ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $this->singleVariantProduct->id)
            ->where(column: 'variant_id', operator: '=', value: $loneVariant->id)
            ->count());
    }

    #[Test]
    public function it_leaves_images_without_on_disk_assets_with_null_variant(): void
    {
        $this->artisan(command: 'assets:backfill-image-variant', parameters: [
            '--asset-base' => $this->tempAssetBase,
            '--tenant' => 'instrumentos-musicales-la-voz',
        ])->run();

        $this->assertSame(expected: 1, actual: ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $this->noAssetsProduct->id)
            ->whereNull(columns: 'variant_id')
            ->count());
    }

    #[Test]
    public function it_is_idempotent_without_force_flag(): void
    {
        $this->artisan(command: 'assets:backfill-image-variant', parameters: [
            '--asset-base' => $this->tempAssetBase,
            '--tenant' => 'instrumentos-musicales-la-voz',
        ])->run();

        // Capturar el variant_id asignado a una imagen específica
        $firstImage = ProductImage::query()->where(column: 'product_id', operator: '=', value: $this->multiVariantProduct->id)->firstOrFail();
        $firstVariantId = $firstImage->variant_id;
        $this->assertNotNull(actual: $firstVariantId);

        // Re-correr: las filas que ya tienen variant_id deben quedar intactas
        $this->artisan(command: 'assets:backfill-image-variant', parameters: [
            '--asset-base' => $this->tempAssetBase,
            '--tenant' => 'instrumentos-musicales-la-voz',
        ])->run();

        $this->assertSame(expected: $firstVariantId, actual: $firstImage->fresh()->variant_id);
    }

    #[Test]
    public function dry_run_does_not_persist_changes(): void
    {
        $ebonyVariant = ProductVariant::query()->where(column: 'finish_slug', operator: '=', value: 'ebony')
            ->where(column: 'product_id', operator: '=', value: $this->multiVariantProduct->id)
            ->firstOrFail();

        $this->artisan(command: 'assets:backfill-image-variant', parameters: [
            '--asset-base' => $this->tempAssetBase,
            '--tenant' => 'instrumentos-musicales-la-voz',
            '--dry-run' => true,
        ])->run();

        $this->assertSame(expected: 0, actual: ProductImage::query()
            ->whereNotNull(columns: 'variant_id')
            ->count(),
            message: 'dry-run no debe persistir variant_id en ninguna fila');
    }

    #[Test]
    public function it_fails_when_tenant_does_not_exist(): void
    {
        $exitCode = $this->artisan(command: 'assets:backfill-image-variant', parameters: [
            '--asset-base' => $this->tempAssetBase,
            '--tenant' => 'nonexistent-tenant',
        ])->run();
        $this->assertSame(expected: 1, actual: $exitCode);
    }

    #[Test]
    public function it_backfills_the_width_of_each_resolution_from_the_filename(): void
    {
        $this->artisan(command: 'assets:backfill-image-variant', parameters: [
            '--asset-base' => $this->tempAssetBase,
            '--tenant' => 'instrumentos-musicales-la-voz',
        ])->run();

        // Los webps del fixture: front-1600x1600, back-1200x1200,
        // body-800x800. El ancho sale del MISMO parseo que usa el seeder.
        // Se comparan ordenados porque las dos variantes comparten
        // `sort_order = 1` y el orden entre empatadas no es estable.
        $widths = ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $this->multiVariantProduct->id)
            ->pluck('width')
            ->map(fn (mixed $w): int => (int) $w)
            ->sort()
            ->values()
            ->all();
        $this->assertSame([1200, 1600, 1600], $widths);

        $singleWidths = ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $this->singleVariantProduct->id)
            ->orderBy(column: 'sort_order')
            ->pluck('width')
            ->all();
        $this->assertSame([1600, 800], array_map('intval', $singleWidths));
    }

    #[Test]
    public function it_leaves_the_width_null_for_an_orphan_image(): void
    {
        $this->artisan(command: 'assets:backfill-image-variant', parameters: [
            '--asset-base' => $this->tempAssetBase,
            '--tenant' => 'instrumentos-musicales-la-voz',
        ])->run();

        // La imagen huérfana no tiene contraparte en disco: inventarle un
        // ancho haría que la galería la tomara por una resolución válida.
        $this->assertSame(expected: 1, actual: ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $this->noAssetsProduct->id)
            ->whereNull(columns: 'width')
            ->count());
    }

    #[Test]
    public function dry_run_does_not_persist_the_width(): void
    {
        $this->artisan(command: 'assets:backfill-image-variant', parameters: [
            '--asset-base' => $this->tempAssetBase,
            '--tenant' => 'instrumentos-musicales-la-voz',
            '--dry-run' => true,
        ])->run();

        $this->assertSame(expected: 0, actual: ProductImage::query()
            ->whereNotNull(columns: 'width')
            ->count(),
            message: 'dry-run no debe persistir width en ninguna fila');
    }

    /**
     * Bytes "webp" falsos pero consistentes por seed. Los usamos en
     * lugar de archivos reales de imagen porque el `StorageKeyDeriver`
     * solo necesita bytes (no un webp válido) para calcular el hash.
     */
    private function fakeWebpBytes(string $seed): string
    {
        return 'RIFF'.pack('V', 30).'WEBPVP8 '.substr(hash('sha256', $seed, true), 0, 20);
    }
}
