<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use App\Services\Catalog\VariantFinishDeriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del comando `catalog:backfill-variant-finish` (Oct 2026).
 *
 * Cubre:
 *   - Variante con `finish_slug == products.slug` en categoría
 *     `guitars`: deriva el acabado desde el vocabulario del nombre.
 *   - Variante con subdirectorio de variante en disco: el nombre del
 *     directorio manda sobre el vocabulario (estrategia 1 sobre 2).
 *   - Variante que YA tiene acabado real: no se toca aunque el
 *     nombre sugiera otro color.
 *   - Producto multi-acabado en disco: no se pisa (ya viene bien del
 *     seeder).
 *   - Categoría sin concepto de acabado (`cases`, `apparel`, …): se
 *     saltea y se cuenta aparte.
 *   - Nombre fuera del vocabulario: degradación honesta, deja la fila
 *     como estaba en vez de inventar un acabado.
 *   - Colisión de slug contra una variante hermana: se saltea.
 *   - `--dry-run`: no escribe.
 *   - Idempotencia: la segunda corrida reporta 0 actualizadas.
 */
#[CoversClass(\App\Console\Commands\BackfillVariantFinishCommand::class)]
#[CoversClass(VariantFinishDeriver::class)]
#[Group('console')]
#[Group('catalog')]
final class BackfillVariantFinishCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $tempAssetBase;

    private Category $guitars;

    private Category $cases;

    private Brand $brand;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempAssetBase = sys_get_temp_dir().'/atlas-finish-test-'.uniqid();

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
        $this->cases = Category::query()->create(attributes: [
            'name' => 'Cases',
            'slug' => 'cases',
            'is_active' => true,
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
    public function it_derives_the_finish_from_the_product_name(): void
    {
        $variant = $this->makeFallbackVariant(
            slug: '1960-les-paul-standard-reissue-iced-tea-burst-left-handed',
            name: '1960 Les Paul Standard Reissue Iced Tea Burst Left Handed (Epiphone)',
        );

        $this->artisan(command: 'catalog:backfill-variant-finish', parameters: [
            '--asset-base' => $this->tempAssetBase,
        ])->run();

        $fresh = $variant->fresh();
        $this->assertSame(expected: 'iced-tea-burst', actual: $fresh?->finish_slug);
        $this->assertSame(expected: 'Iced Tea Burst', actual: $fresh?->finish_label);
    }

    #[Test]
    public function it_prefers_the_longest_vocabulary_match(): void
    {
        // "Vintage Cherry Sunburst" tiene que ganarle a "Cherry".
        $variant = $this->makeFallbackVariant(
            slug: 'es-335-figured-with-rosewood-fretboard-vintage-cherry-sunburst',
            name: 'Es 335 Figured With Rosewood Fretboard Vintage Cherry Sunburst (Epiphone)',
        );

        $this->artisan(command: 'catalog:backfill-variant-finish', parameters: [
            '--asset-base' => $this->tempAssetBase,
        ])->run();

        $this->assertSame(
            expected: 'vintage-cherry-sunburst',
            actual: $variant->fresh()?->finish_slug,
        );
    }

    #[Test]
    public function it_prefers_the_asset_variant_dir_over_the_name_vocabulary(): void
    {
        $variant = $this->makeFallbackVariant(
            slug: 'test-masterbilt-excellente',
            name: 'Test Masterbilt Excelente Vintage Sunburst (Epiphone)',
        );
        $this->makeAssetDir(
            productSlug: 'test-masterbilt-excellente',
            variantSlug: 'antique-natural-aged',
        );

        $this->artisan(command: 'catalog:backfill-variant-finish', parameters: [
            '--asset-base' => $this->tempAssetBase,
        ])
            ->expectsOutputToContain('desde subdirectorio de assets:   1')
            ->run();

        $this->assertSame(expected: 'antique-natural-aged', actual: $variant->fresh()?->finish_slug);
    }

    #[Test]
    public function it_never_overwrites_a_variant_that_already_has_a_real_finish(): void
    {
        $product = $this->makeProduct(
            slug: 'hummingbird-standard',
            name: 'Hummingbird Standard Purple Burst (Epiphone)',
        );
        $variant = $this->makeVariant(
            product: $product,
            sku: 'HB-PURPLE',
            finishSlug: 'purple-burst',
            finishLabel: 'Purple Burst',
        );

        $this->artisan(command: 'catalog:backfill-variant-finish', parameters: [
            '--asset-base' => $this->tempAssetBase,
        ])
            ->expectsOutputToContain('ya con acabado real (intactas):  1')
            ->run();

        $fresh = $variant->fresh();
        $this->assertSame(expected: 'purple-burst', actual: $fresh?->finish_slug);
        $this->assertSame(expected: 'Purple Burst', actual: $fresh?->finish_label);
    }

    #[Test]
    public function it_does_not_touch_multi_finish_products_that_already_have_asset_dirs(): void
    {
        $product = $this->makeProduct(slug: 'test-multi', name: 'Test Multi Ebony');
        $variant = $this->makeVariant(
            product: $product,
            sku: 'TEST-MULTI-EBONY',
            finishSlug: 'test-multi',
            finishLabel: 'Test Multi Ebony',
        );
        $this->makeAssetDir(productSlug: 'test-multi', variantSlug: 'ebony');
        $this->makeAssetDir(productSlug: 'test-multi', variantSlug: 'cherry');

        $this->artisan(command: 'catalog:backfill-variant-finish', parameters: [
            '--asset-base' => $this->tempAssetBase,
        ])
            ->expectsOutputToContain('Omitidas por multi-acabado en disco: 1')
            ->run();

        // El seeder ya resolvió bien este caso; pisarlo sería una
        // regresión, no una mejora. Además la fila no dice cuál de los
        // dos acabados es: adivinar sería inventar.
        $this->assertSame(expected: 'test-multi', actual: $variant->fresh()?->finish_slug);
    }

    #[Test]
    public function it_skips_categories_where_finish_is_not_a_domain_concept(): void
    {
        $product = $this->makeProduct(
            slug: 'premium-gigbag-les-paul-sg-black',
            name: 'Premium Gigbag Les Paul Sg Black',
            category: $this->cases,
        );
        $variant = $this->makeVariant(
            product: $product,
            sku: 'GIGBAG-BLACK',
            finishSlug: 'premium-gigbag-les-paul-sg-black',
            finishLabel: 'Premium Gigbag Les Paul Sg Black',
        );

        $this->artisan(command: 'catalog:backfill-variant-finish', parameters: [
            '--asset-base' => $this->tempAssetBase,
        ])
            ->expectsOutputToContain('Omitidas por categoría sin acabado: 1')
            ->run();

        $this->assertSame(
            expected: 'premium-gigbag-les-paul-sg-black',
            actual: $variant->fresh()?->finish_slug,
        );
    }

    #[Test]
    public function it_leaves_names_outside_the_vocabulary_untouched(): void
    {
        $variant = $this->makeFallbackVariant(
            slug: 'baretta-custom-graphics-time-machine',
            name: 'Baretta Custom Graphics Time Machine (Kramer)',
        );

        $this->artisan(command: 'catalog:backfill-variant-finish', parameters: [
            '--asset-base' => $this->tempAssetBase,
        ])
            ->expectsOutputToContain('Sin acabado derivable (degradan):  1')
            ->run();

        $fresh = $variant->fresh();
        $this->assertSame(expected: 'baretta-custom-graphics-time-machine', actual: $fresh?->finish_slug);
        $this->assertSame(
            expected: 'Baretta Custom Graphics Time Machine (Kramer)',
            actual: $fresh?->finish_label,
        );
    }

    #[Test]
    public function it_skips_a_derived_slug_that_collides_with_a_sibling_variant(): void
    {
        $product = $this->makeProduct(
            slug: 'test-collision',
            name: 'Test Collision Vintage Sunburst Ebony (Epiphone)',
        );
        $sibling = $this->makeVariant(
            product: $product,
            sku: 'TEST-COLLISION-EBONY',
            finishSlug: 'vintage-sunburst',
            finishLabel: 'Vintage Sunburst',
        );
        $variant = $this->makeVariant(
            product: $product,
            sku: 'TEST-COLLISION-2',
            finishSlug: 'test-collision',
            finishLabel: 'Test Collision Vintage Sunburst Ebony',
        );

        $this->artisan(command: 'catalog:backfill-variant-finish', parameters: [
            '--asset-base' => $this->tempAssetBase,
        ])
            ->expectsOutputToContain('Omitidas por colisión de slug:    1')
            ->run();

        $this->assertSame(expected: 'test-collision', actual: $variant->fresh()?->finish_slug);
        $this->assertSame(expected: 'vintage-sunburst', actual: $sibling->fresh()?->finish_slug);
    }

    #[Test]
    public function it_does_not_write_when_dry_run_is_passed(): void
    {
        $variant = $this->makeFallbackVariant(
            slug: 'es-335-vintage-sunburst',
            name: 'Es 335 Vintage Sunburst (Epiphone)',
        );

        $exitCode = $this->artisan(
            command: 'catalog:backfill-variant-finish',
            parameters: ['--dry-run' => true, '--asset-base' => $this->tempAssetBase],
        )->run();

        $this->assertSame(expected: 0, actual: $exitCode);
        $this->assertSame(expected: 'es-335-vintage-sunburst', actual: $variant->fresh()?->finish_slug);
    }

    #[Test]
    public function it_reports_zero_updates_on_a_second_run(): void
    {
        $this->makeFallbackVariant(
            slug: 'sg-tribute-plus-vintage-sunburst',
            name: 'Sg Tribute Plus Vintage Sunburst (Epiphone)',
        );

        $this->artisan(command: 'catalog:backfill-variant-finish', parameters: [
            '--asset-base' => $this->tempAssetBase,
        ])->run();

        $this->artisan(command: 'catalog:backfill-variant-finish', parameters: [
            '--asset-base' => $this->tempAssetBase,
        ])
            ->expectsOutputToContain('Variantes actualizadas:            0')
            ->assertSuccessful();
    }

    #[Test]
    public function it_does_not_invent_a_finish_from_a_partial_word(): void
    {
        $variant = $this->makeFallbackVariant(
            slug: 'test-chernobyl-ian-moyes-fusion',
            name: 'Test Chernobyl Ian Moyes Fusion (Epiphone)',
        );

        $this->artisan(command: 'catalog:backfill-variant-finish', parameters: [
            '--asset-base' => $this->tempAssetBase,
        ])->run();

        $this->assertSame(expected: 'test-chernobyl-ian-moyes-fusion', actual: $variant->fresh()?->finish_slug);
    }

    private function makeProduct(
        string $slug,
        string $name,
        ?Category $category = null,
    ): Product {
        return Product::query()->create(attributes: [
            'name' => $name,
            'slug' => $slug,
            'sku' => strtoupper($slug),
            'description' => 'Test product',
            'category_id' => ($category ?? $this->guitars)->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'base_price_cents' => 100000,
            'is_active' => true,
        ]);
    }

    private function makeVariant(
        Product $product,
        string $sku,
        string $finishSlug,
        string $finishLabel,
    ): ProductVariant {
        return ProductVariant::query()->create(attributes: [
            'product_id' => $product->id,
            'name' => $finishLabel,
            'sku' => strtoupper($sku),
            'price_cents' => 100000,
            'is_active' => true,
            'finish_slug' => $finishSlug,
            'finish_label' => $finishLabel,
            'condition' => 'nuevo',
            'stock' => 0,
        ]);
    }

    /**
     * Variante en el estado "fallback": su finish es el slug del
     * producto, exactamente el caso que el comando viene a arreglar.
     */
    private function makeFallbackVariant(string $slug, string $name): ProductVariant
    {
        $product = $this->makeProduct(slug: $slug, name: $name);

        return $this->makeVariant(
            product: $product,
            sku: $slug,
            finishSlug: $slug,
            finishLabel: $name,
        );
    }

    private function makeAssetDir(string $productSlug, string $variantSlug): void
    {
        $dir = "{$this->tempAssetBase}/products/instrumentos-musicales-la-voz/epiphone/{$productSlug}/{$variantSlug}";
        if (! is_dir($dir)) {
            mkdir(directory: $dir, permissions: 0755, recursive: true);
        }
        file_put_contents($dir.'/front-1600x1600.webp', 'fake-webp');
    }
}
