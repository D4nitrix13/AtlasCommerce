<?php

declare(strict_types=1);

namespace Tests\Feature\Assets;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductSize;
use App\Models\Catalog\ProductVariant;
use App\Services\Assets\AssetFilenameParser;
use App\Services\Assets\CatalogProductImageSeeder;
use App\Services\Assets\StorageKeyDeriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de `CatalogProductImageSeeder` — el seeder de imágenes del
 * catálogo Panda/Kitsune (FTP + `product_images`).
 *
 * Regresiones que cubren:
 *   - Las fotos de un finish cuelgan de TODAS las variantes de talla
 *     que lo comparten: la PDP filtra `img.variantId ===
 *     selectedVariantId`, y colgar las fotos de una sola variante deja
 *     al resto con galería vacía.
 *   - `is_primary` sólo en la fila `role=front size=1600` de cada
 *     variante, `is_hover` sólo en las filas `role=back` (todas las
 *     resoluciones).
 *   - Idempotencia por `(product_id, variant_id, image_url)`.
 *   - Warning + skip (sin crear) cuando el producto o la variante no
 *     existen: el seeder no crea catálogo, sólo imágenes.
 *
 * ⚠️ Cada webp fixture necesita bytes DISTINTOS: el `storage_key` es
 *    el SHA-256 del binario, así que dos archivos con el mismo
 *    contenido colapsan en la misma fila de `product_images`.
 */
final class CatalogProductImageSeederTest extends TestCase
{
    use RefreshDatabase;

    private const TENANT = 'panda-estampados';

    private string $tempAssetBase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local-fake');

        $this->tempAssetBase = sys_get_temp_dir().'/atlas-product-images-'.uniqid();
        mkdir($this->tempAssetBase.'/products/'.self::TENANT, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempAssetBase)) {
            exec(sprintf('rm -rf %s', escapeshellarg($this->tempAssetBase)));
        }

        parent::tearDown();
    }

    #[Test]
    public function it_creates_image_rows_for_every_size_variant_sharing_a_finish(): void
    {
        $product = $this->makeProduct(slug: 'playera-manga-corta');
        $variantCh = $this->makeVariant(product: $product, finish: 'blanco', sizeSlug: 'ch');
        $variantM = $this->makeVariant(product: $product, finish: 'blanco', sizeSlug: 'm');

        $this->webp('panda/playera-manga-corta/blanco/front-1600x1600.webp');
        $this->webp('panda/playera-manga-corta/blanco/back-1600x1600.webp');

        $this->runSeeder();

        // 2 fotos × 2 variantes de talla = 2 juegos de filas completos.
        $this->assertSame(
            [2, 2],
            [
                ProductImage::query()->where('variant_id', $variantCh->id)->count(),
                ProductImage::query()->where('variant_id', $variantM->id)->count(),
            ],
        );
    }

    #[Test]
    public function it_marks_is_primary_only_on_the_1600_of_the_primary_role_of_each_variant(): void
    {
        $product = $this->makeProduct(slug: 'playera-manga-corta');
        $variantCh = $this->makeVariant(product: $product, finish: 'blanco', sizeSlug: 'ch');
        $variantM = $this->makeVariant(product: $product, finish: 'blanco', sizeSlug: 'm');

        $this->webp('panda/playera-manga-corta/blanco/front-1600x1600.webp');
        $this->webp('panda/playera-manga-corta/blanco/front-400x400.webp');
        $this->webp('panda/playera-manga-corta/blanco/back-1600x1600.webp');

        $this->runSeeder();

        // Una sola primary por variante, y es la de mayor resolución del
        // rol principal (front), nunca las otras 3 resoluciones ni back.
        $primaryPerVariant = ProductImage::query()
            ->where('is_primary', true)
            ->get()
            ->map(static fn (ProductImage $image): string => "{$image->variant_id}:{$image->width}")
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ["{$variantCh->id}:1600", "{$variantM->id}:1600"],
            $primaryPerVariant,
        );
    }

    #[Test]
    public function it_marks_is_hover_only_on_back_rows_across_all_widths(): void
    {
        $product = $this->makeProduct(slug: 'playera-manga-corta');
        $this->makeVariant(product: $product, finish: 'blanco', sizeSlug: null);

        $this->webp('panda/playera-manga-corta/blanco/back-1600x1600.webp');
        $this->webp('panda/playera-manga-corta/blanco/back-400x400.webp');
        $this->webp('panda/playera-manga-corta/blanco/front-1600x1600.webp');

        $this->runSeeder();

        // Las 4 resoluciones de la foto back llevan el flag; el resto no.
        $hoverWidths = ProductImage::query()
            ->where('is_hover', true)
            ->orderBy('width')
            ->pluck('width')
            ->all();

        $this->assertSame([400, 1600], $hoverWidths);
    }

    #[Test]
    public function it_persists_the_role_order_and_the_width_of_each_row(): void
    {
        $product = $this->makeProduct(slug: 'playera-manga-corta');
        $this->makeVariant(product: $product, finish: 'blanco', sizeSlug: null);

        $this->webp('panda/playera-manga-corta/blanco/front-1200x1200.webp');
        $this->webp('panda/playera-manga-corta/blanco/back-800x800.webp');

        $this->runSeeder();

        // front → sort_order 1, back → 2 (ROLE_ORDER), width = ancho real.
        $rows = ProductImage::query()
            ->orderBy('sort_order')
            ->get()
            ->map(static fn (ProductImage $image): array => [$image->sort_order, $image->width])
            ->all();

        $this->assertSame([[1, 1200], [2, 800]], $rows);
    }

    #[Test]
    public function it_does_not_duplicate_rows_on_a_second_run(): void
    {
        $product = $this->makeProduct(slug: 'playera-manga-corta');
        $this->makeVariant(product: $product, finish: 'blanco', sizeSlug: null);

        $this->webp('panda/playera-manga-corta/blanco/front-1600x1600.webp');
        $this->webp('panda/playera-manga-corta/blanco/back-1600x1600.webp');

        $this->runSeeder();
        $this->runSeeder();

        // updateOrCreate por (product_id, variant_id, image_url): la
        // segunda corrida actualiza, no inserta.
        $this->assertSame(2, ProductImage::query()->count());
    }

    #[Test]
    public function it_warns_and_creates_nothing_when_the_product_is_missing(): void
    {
        $this->webp('panda/producto-inexistente/blanco/front-1600x1600.webp');

        $stats = $this->runSeeder();

        $this->assertCount(1, $stats['warnings']);
        $this->assertSame(0, ProductImage::query()->count());
    }

    #[Test]
    public function it_warns_and_creates_nothing_when_no_variant_matches_the_finish(): void
    {
        $product = $this->makeProduct(slug: 'playera-manga-corta');
        $this->makeVariant(product: $product, finish: 'blanco', sizeSlug: null);

        $this->webp('panda/playera-manga-corta/negro/front-1600x1600.webp');

        $stats = $this->runSeeder();

        $this->assertCount(1, $stats['warnings']);
        $this->assertSame(0, ProductImage::query()->count());
    }

    #[Test]
    public function it_warns_and_skips_files_whose_name_does_not_parse(): void
    {
        $product = $this->makeProduct(slug: 'playera-manga-corta');
        $this->makeVariant(product: $product, finish: 'blanco', sizeSlug: null);

        $this->webp('panda/playera-manga-corta/blanco/sin-rol-valido.webp');

        $stats = $this->runSeeder();

        $this->assertCount(1, $stats['warnings']);
        $this->assertSame(0, ProductImage::query()->count());
    }

    #[Test]
    public function it_creates_nothing_and_uploads_nothing_in_dry_run_mode(): void
    {
        $product = $this->makeProduct(slug: 'playera-manga-corta');
        $this->makeVariant(product: $product, finish: 'blanco', sizeSlug: null);

        $this->webp('panda/playera-manga-corta/blanco/front-1600x1600.webp');

        $stats = $this->runSeeder(dryRun: true);

        // Reporta lo que haría, pero no sube ni inserta.
        $this->assertSame(1, $stats['images_created']);
        $this->assertSame(0, ProductImage::query()->count());
        Storage::disk('local-fake')->assertDirectoryEmpty('tenants');
    }

    #[Test]
    public function it_seeds_through_the_assets_seed_images_command(): void
    {
        $product = $this->makeProduct(slug: 'playera-manga-corta');
        $this->makeVariant(product: $product, finish: 'blanco', sizeSlug: null);

        $this->webp('panda/playera-manga-corta/blanco/front-1600x1600.webp');
        $this->webp('panda/playera-manga-corta/blanco/back-1600x1600.webp');

        // El wiring del command (asset-base / tenant / disk) es el único
        // camino al seeder en producción.
        $this->artisan('assets:seed-images', [
            '--tenant' => self::TENANT,
            '--asset-base' => $this->tempAssetBase,
            '--storage-disk' => 'local-fake',
        ])->assertExitCode(0);

        $this->assertSame(2, ProductImage::query()->count());
    }

    /**
     * Crea un webp con bytes únicos dentro del árbol del tenant.
     */
    private function webp(string $relativePath): string
    {
        $full = $this->tempAssetBase.'/products/'.self::TENANT.'/'.$relativePath;
        $dir = dirname($full);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($full, 'RIFF'.random_bytes(4).'WEBPVP8 '.random_bytes(24));

        return $full;
    }

    private function makeProduct(string $slug): Product
    {
        return Product::factory()->create(['slug' => $slug]);
    }

    /**
     * Variante de un finish. `sizeSlug` no nulo crea la `ProductSize`
     * y la referencia: el unique de variantes es
     * `(product_id, finish_slug, size_id) NULLS NOT DISTINCT`, así que
     * dos variantes del mismo finish necesitan tallas distintas.
     */
    private function makeVariant(Product $product, string $finish, ?string $sizeSlug): ProductVariant
    {
        $sizeId = null;
        if ($sizeSlug !== null) {
            $sizeId = ProductSize::query()->create([
                'product_id' => $product->id,
                'slug' => $sizeSlug,
                'label' => strtoupper($sizeSlug),
            ])->id;
        }

        return ProductVariant::factory()->for($product, 'product')->create([
            'finish_slug' => $finish,
            'finish_label' => ucfirst($finish),
            'size_id' => $sizeId,
        ]);
    }

    /**
     * Corre el seeder real (sin artisan) y devuelve sus stats.
     *
     * @return array{
     *   images_uploaded: int,
     *   images_skipped: int,
     *   images_created: int,
     *   errors: array<int, string>,
     *   warnings: array<int, string>,
     * }
     */
    private function runSeeder(bool $dryRun = false): array
    {
        $seeder = new CatalogProductImageSeeder(
            assetBase: $this->tempAssetBase,
            tenantSlug: self::TENANT,
            storageDisk: 'local-fake',
            deriver: new StorageKeyDeriver,
            filenames: new AssetFilenameParser,
            dryRun: $dryRun,
        );

        return $seeder->seed();
    }
}
