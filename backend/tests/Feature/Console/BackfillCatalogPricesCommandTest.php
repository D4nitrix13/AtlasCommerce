<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del comando `catalog:backfill-prices`.
 *
 * El comando une el árbol de assets con `catalog-meta` por sufijo de path
 * relativo a la categoría (`{brand}/{producto}[/{variante}]`), no por el
 * `handle` del JSON. Estos tests fijan ese contrato con los dos árboles
 * reales en disco temporal:
 *
 *   - assets: `{base}/products/{tenant}/{brand}/{producto}[/{variante}]/`
 *   - meta:   `{base}/{categoría}/{brand}/{producto}[/{variante}]/product.json`
 *
 * Cubre:
 *   - Producto de una sola variante (webps directos, sin subdirectorios):
 *     precio de producto y de variante desde `price.regular`.
 *   - Producto multi-acabado: cada variante con su precio y el producto con
 *     el mínimo ("precio desde").
 *   - Slug con la marca pegada: fallback `stripBrandPrefix`.
 *   - Producto sin entrada en `catalog-meta`: el precio queda como estaba,
 *     no en 0.
 *   - `--dry-run`: ni producto ni variante se tocan.
 *   - Idempotencia: la segunda corrida no reporta cambios.
 */
#[CoversClass(\App\Console\Commands\BackfillCatalogPricesCommand::class)]
#[Group('console')]
#[Group('catalog')]
final class BackfillCatalogPricesCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El comando resuelve el tenant contra el subdirectorio del árbol de
     * assets, así que el fixture tiene que usar el mismo slug que el
     * default de `--tenant`.
     */
    private const TENANT = 'instrumentos-musicales-la-voz';

    private string $tempAssetBase;

    private string $tempMetaBase;

    private Category $guitars;

    private Brand $brand;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid();
        $this->tempAssetBase = sys_get_temp_dir()."/atlas-prices-assets-{$suffix}";
        $this->tempMetaBase = sys_get_temp_dir()."/atlas-prices-meta-{$suffix}";

        mkdir(directory: $this->tempAssetBase, permissions: 0755, recursive: true);
        mkdir(directory: $this->tempMetaBase, permissions: 0755, recursive: true);

        $this->currency = Currency::query()->create(attributes: [
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'is_active' => true,
            'is_primary' => true,
        ]);
        $this->brand = Brand::query()->create(attributes: [
            'name' => 'Epiphone',
            'slug' => 'epiphone',
            'is_active' => true,
        ]);
        $this->guitars = Category::query()->create(attributes: [
            'name' => 'Guitars',
            'slug' => 'guitars',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->tempAssetBase, $this->tempMetaBase] as $dir) {
            if (is_dir(filename: $dir)) {
                exec(sprintf('rm -rf %s', escapeshellarg(arg: $dir)));
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function it_backfills_product_and_variant_price_from_catalog_meta(): void
    {
        $product = $this->makeProduct(slug: 'masterbilt-texan-faded-cherry');
        $variant = $this->makeVariant(product: $product, sku: 'MBT-FADED-CHERRY', finishSlug: 'masterbilt-texan-faded-cherry');

        $this->makeSingleVariantAssetDir(productSlug: 'masterbilt-texan-faded-cherry');
        $this->writeMetaJson(category: 'guitars', brand: 'epiphone', product: 'masterbilt-texan-faded-cherry', regular: 649.99);

        $this->artisan(command: 'catalog:backfill-prices', parameters: $this->params())
            ->assertExitCode(0);

        $this->assertSame(expected: 64999, actual: $product->fresh()?->base_price_cents);
        $this->assertSame(expected: 64999, actual: $variant->fresh()?->price_cents);
    }

    #[Test]
    public function it_prices_each_finish_separately_and_uses_the_minimum_for_the_product(): void
    {
        $product = $this->makeProduct(slug: 'es-335-figured-rosewood');
        $ebony = $this->makeVariant(product: $product, sku: 'ES335-EBONY', finishSlug: 'ebony');
        $burst = $this->makeVariant(product: $product, sku: 'ES335-BURST', finishSlug: 'vintage-burst');

        $this->makeVariantAssetDir(productSlug: 'es-335-figured-rosewood', variantDir: 'ebony');
        $this->makeVariantAssetDir(productSlug: 'es-335-figured-rosewood', variantDir: 'vintage-burst');
        $this->writeMetaJson(category: 'guitars', brand: 'epiphone', product: 'es-335-figured-rosewood', variant: 'ebony', regular: 1299.50);
        $this->writeMetaJson(category: 'guitars', brand: 'epiphone', product: 'es-335-figured-rosewood', variant: 'vintage-burst', regular: 1499.00);

        $this->artisan(command: 'catalog:backfill-prices', parameters: $this->params())
            ->assertExitCode(0);

        $this->assertSame(expected: 129950, actual: $ebony->fresh()?->price_cents);
        $this->assertSame(expected: 149900, actual: $burst->fresh()?->price_cents);
        $this->assertSame(expected: 129950, actual: $product->fresh()?->base_price_cents);
    }

    #[Test]
    public function it_resolves_the_price_when_the_asset_slug_carries_the_brand_prefix(): void
    {
        $product = $this->makeProduct(slug: 'gibson-mod-collection-es-335');
        $variant = $this->makeVariant(product: $product, sku: 'GIBSON-ES335', finishSlug: 'gibson-mod-collection-es-335');

        // El directorio de assets conserva la marca en el slug; el de
        // catalog-meta no. El comando tiene que normalizar para poder unir.
        $this->makeSingleVariantAssetDir(productSlug: 'gibson-mod-collection-es-335', brand: 'gibson');
        $this->writeMetaJson(category: 'guitars', brand: 'gibson', product: 'mod-collection-es-335', regular: 1899.00);

        $this->artisan(command: 'catalog:backfill-prices', parameters: $this->params())
            ->assertExitCode(0);

        $this->assertSame(expected: 189900, actual: $product->fresh()?->base_price_cents);
        $this->assertSame(expected: 189900, actual: $variant->fresh()?->price_cents);
    }

    #[Test]
    public function it_leaves_the_price_untouched_when_the_product_is_not_in_catalog_meta(): void
    {
        $product = $this->makeProduct(slug: 'baretta-custom-graphics-time-machine', basePriceCents: 77777);
        $variant = $this->makeVariant(
            product: $product,
            sku: 'BARETTA-TIME-MACHINE',
            finishSlug: 'baretta-custom-graphics-time-machine',
            priceCents: 88888,
        );

        $this->makeSingleVariantAssetDir(productSlug: 'baretta-custom-graphics-time-machine', brand: 'kramer');

        $this->artisan(command: 'catalog:backfill-prices', parameters: $this->params())
            ->expectsOutputToContain('Sin precio en catalog-meta:      1')
            ->assertExitCode(0);

        // Cero sería peor que el dato que ya había: el comando no inventa.
        $this->assertSame(expected: 77777, actual: $product->fresh()?->base_price_cents);
        $this->assertSame(expected: 88888, actual: $variant->fresh()?->price_cents);
    }

    #[Test]
    public function it_writes_nothing_when_dry_run_is_passed(): void
    {
        $product = $this->makeProduct(slug: 'hummingbird-standard-purple-burst');
        $variant = $this->makeVariant(product: $product, sku: 'HB-PURPLE-BURST', finishSlug: 'hummingbird-standard-purple-burst');

        $this->makeSingleVariantAssetDir(productSlug: 'hummingbird-standard-purple-burst');
        $this->writeMetaJson(category: 'guitars', brand: 'epiphone', product: 'hummingbird-standard-purple-burst', regular: 399.95);

        $this->artisan(command: 'catalog:backfill-prices', parameters: $this->params(dryRun: true))
            ->expectsOutputToContain('DRY-RUN: no se escribió nada.')
            ->assertExitCode(0);

        $this->assertSame(expected: 100000, actual: $product->fresh()?->base_price_cents);
        $this->assertSame(expected: 100000, actual: $variant->fresh()?->price_cents);
    }

    #[Test]
    public function it_reports_zero_updates_on_a_second_run(): void
    {
        $product = $this->makeProduct(slug: 'sg-tribute-plus-vintage-sunburst');
        $this->makeVariant(product: $product, sku: 'SG-TRIBUTE-VS', finishSlug: 'sg-tribute-plus-vintage-sunburst');

        $this->makeSingleVariantAssetDir(productSlug: 'sg-tribute-plus-vintage-sunburst');
        $this->writeMetaJson(category: 'guitars', brand: 'epiphone', product: 'sg-tribute-plus-vintage-sunburst', regular: 749.00);

        $this->artisan(command: 'catalog:backfill-prices', parameters: $this->params())
            ->assertExitCode(0);

        $this->artisan(command: 'catalog:backfill-prices', parameters: $this->params())
            ->expectsOutputToContain('con precio a cambiar:          0')
            ->assertExitCode(0);

        $this->assertSame(expected: 74900, actual: $product->fresh()?->base_price_cents);
    }

    /**
     * @return array<string, string|bool>
     */
    private function params(bool $dryRun = false): array
    {
        $parameters = [
            '--asset-base' => $this->tempAssetBase,
            '--meta-base' => $this->tempMetaBase,
        ];

        if ($dryRun) {
            $parameters['--dry-run'] = true;
        }

        return $parameters;
    }

    private function makeProduct(string $slug, int $basePriceCents = 100000): Product
    {
        return Product::query()->create(attributes: [
            'name' => $slug,
            'slug' => $slug,
            'sku' => strtoupper($slug),
            'description' => 'Test product',
            'category_id' => $this->guitars->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'base_price_cents' => $basePriceCents,
            'is_active' => true,
        ]);
    }

    private function makeVariant(
        Product $product,
        string $sku,
        string $finishSlug,
        int $priceCents = 100000,
    ): ProductVariant {
        return ProductVariant::query()->create(attributes: [
            'product_id' => $product->id,
            'name' => $finishSlug,
            'sku' => strtoupper($sku),
            'price_cents' => $priceCents,
            'is_active' => true,
            'finish_slug' => $finishSlug,
            'finish_label' => $finishSlug,
            'condition' => 'nuevo',
            'stock' => 0,
        ]);
    }

    /**
     * Producto de una sola variante: los webps cuelgan directamente del
     * directorio del producto, sin subdirectorios de acabado.
     */
    private function makeSingleVariantAssetDir(string $productSlug, string $brand = 'epiphone'): void
    {
        $dir = "{$this->tempAssetBase}/products/".self::TENANT."/{$brand}/{$productSlug}";
        $this->makeDir($dir);
        file_put_contents(filename: $dir.'/front-1600x1600.webp', data: 'fake-webp');
    }

    private function makeVariantAssetDir(string $productSlug, string $variantDir, string $brand = 'epiphone'): void
    {
        $dir = "{$this->tempAssetBase}/products/".self::TENANT."/{$brand}/{$productSlug}/{$variantDir}";
        $this->makeDir($dir);
        file_put_contents(filename: $dir.'/front-1600x1600.webp', data: 'fake-webp');
    }

    /**
     * La categoría es un segmento real del árbol pero queda fuera de la
     * clave del índice: el árbol de assets no la tiene.
     */
    private function writeMetaJson(string $category, string $brand, string $product, float $regular, ?string $variant = null): void
    {
        $segments = [$category, $brand, $product];

        if ($variant !== null) {
            $segments[] = $variant;
        }

        $dir = $this->tempMetaBase.'/'.implode(separator: '/', array: $segments);
        $this->makeDir($dir);

        file_put_contents(
            filename: $dir.'/product.json',
            data: (string) json_encode(value: [
                'title' => $product,
                'vendor' => $brand,
                'handle' => $brand.'-'.$product,
                'price' => [
                    'regular' => $regular,
                    'currency' => 'USD',
                    'compare_at' => null,
                ],
            ], flags: JSON_PRETTY_PRINT),
        );
    }

    private function makeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir(directory: $dir, permissions: 0755, recursive: true);
        }
    }
}
