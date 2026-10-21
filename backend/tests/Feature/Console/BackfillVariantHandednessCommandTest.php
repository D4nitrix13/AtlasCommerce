<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use App\Services\Catalog\VariantHandednessResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del comando `catalog:backfill-handedness` (Oct 2026).
 *
 * Cubre:
 *   - Producto cuyo slug dice "left-handed": todas sus variantes quedan
 *     en `'left-handed'` (la mano es del modelo completo, no de la
 *     variante).
 *   - Producto cuyo NOMBRE dice "Left-Handed" pero el slug no
 *     (caso `malina-moye-pacer-deluxe-left-handed` del catálogo real):
 *     igual se puebla.
 *   - Producto diestro: la variante queda en NULL, no en 'right-handed'.
 *   - `--dry-run`: calcula, reporta y NO escribe.
 *   - Idempotencia: la segunda corrida reporta 0 actualizadas.
 *   - El reporte delata el flag stale de `products.is_left_handed`.
 *   - `--product=` acota a un solo producto.
 */
#[CoversClass(\App\Console\Commands\BackfillVariantHandednessCommand::class)]
#[CoversClass(VariantHandednessResolver::class)]
#[Group('console')]
#[Group('catalog')]
final class BackfillVariantHandednessCommandTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $leftHandedFromSlug;

    private ProductVariant $leftHandedFromName;

    private ProductVariant $rightHanded;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::query()->create(attributes: [
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'is_active' => true,
            'is_primary' => true,
        ]);
        $brand = Brand::query()->create(attributes: [
            'name' => 'Epiphone',
            'slug' => 'epiphone',
            'is_active' => true,
        ]);
        $category = Category::query()->create(attributes: [
            'name' => 'Guitars',
            'slug' => 'guitars',
            'is_active' => true,
        ]);

        $makeVariant = function (string $slug, string $name, string $sku) use ($brand, $category, $currency): array {
            $product = Product::query()->create(attributes: [
                'name' => $name,
                'slug' => $slug,
                'sku' => strtoupper($sku),
                'description' => 'Test product',
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'currency_id' => $currency->id,
                'base_price_cents' => 100000,
                'is_active' => true,
            ]);

            $variant = ProductVariant::query()->create(attributes: [
                'product_id' => $product->id,
                'name' => $name,
                'sku' => strtoupper($sku).'-V1',
                'price_cents' => 100000,
                'is_active' => true,
                'finish_slug' => 'ebony',
                'finish_label' => 'Ebony',
                'condition' => 'nuevo',
                'stock' => 0,
            ]);

            return [$product, $variant];
        };

        [, $this->leftHandedFromSlug] = $makeVariant(
            slug: 'test-les-paul-iced-tea-burst-left-handed',
            name: 'Test Les Paul Iced Tea Burst Left Handed (Epiphone)',
            sku: 'TEST-LH-SLUG',
        );

        [, $this->leftHandedFromName] = $makeVariant(
            slug: 'test-pacer-deluxe-splash-blue',
            name: 'Test Pacer Deluxe, Left-Handed, Splash Blue',
            sku: 'TEST-LH-NAME',
        );

        [, $this->rightHanded] = $makeVariant(
            slug: 'test-les-paul-standard-vintage-sunburst',
            name: 'Test Les Paul Standard Vintage Sunburst (Epiphone)',
            sku: 'TEST-RH',
        );
    }

    #[Test]
    public function it_populates_left_handed_when_the_product_slug_says_so(): void
    {
        $this->artisan(command: 'catalog:backfill-handedness')->run();

        $this->assertSame(
            expected: VariantHandednessResolver::LEFT_HANDED,
            actual: $this->leftHandedFromSlug->fresh()?->handedness,
        );
    }

    #[Test]
    public function it_populates_left_handed_when_only_the_product_name_says_so(): void
    {
        $this->artisan(command: 'catalog:backfill-handedness')->run();

        $this->assertSame(
            expected: VariantHandednessResolver::LEFT_HANDED,
            actual: $this->leftHandedFromName->fresh()?->handedness,
        );
    }

    #[Test]
    public function it_leaves_right_handed_variants_null(): void
    {
        $this->artisan(command: 'catalog:backfill-handedness')->run();

        $this->assertNull($this->rightHanded->fresh()?->handedness);
    }

    #[Test]
    public function it_does_not_write_when_dry_run_is_passed(): void
    {
        $exitCode = $this->artisan(
            command: 'catalog:backfill-handedness',
            parameters: ['--dry-run' => true],
        )->run();

        $this->assertSame(expected: 0, actual: $exitCode);
        $this->assertNull($this->leftHandedFromSlug->fresh()?->handedness);
        $this->assertNull($this->leftHandedFromName->fresh()?->handedness);
    }

    #[Test]
    public function it_reports_zero_updates_on_a_second_run(): void
    {
        $this->artisan(command: 'catalog:backfill-handedness')->run();

        $secondRun = $this->artisan(command: 'catalog:backfill-handedness')
            ->expectsOutputToContain('Variantes actualizadas:   0')
            ->run();

        $this->assertSame(expected: 0, actual: $secondRun);
    }

    #[Test]
    public function it_reports_the_stale_products_left_handed_flag(): void
    {
        // `products.is_left_handed` queda en el default `false` de la
        // migración mientras el texto del producto sí dice zurdo: el
        // reporte tiene que delatar esa discrepancia en vez de
        // resolverla en silencio.
        $this->assertFalse(
            (bool) $this->leftHandedFromSlug->product?->is_left_handed,
            'El fixture debe arrancar con el flag stale para que el test signifique algo.',
        );

        $this->artisan(command: 'catalog:backfill-handedness')
            ->expectsOutputToContain('Productos con nombre "left-handed" y products.is_left_handed = false: 2')
            ->assertSuccessful();
    }

    #[Test]
    public function it_limits_the_run_to_the_given_product(): void
    {
        $this->artisan(
            command: 'catalog:backfill-handedness',
            parameters: ['--product' => 'test-les-paul-iced-tea-burst-left-handed'],
        )->run();

        $this->assertSame(
            expected: VariantHandednessResolver::LEFT_HANDED,
            actual: $this->leftHandedFromSlug->fresh()?->handedness,
        );
        $this->assertNull($this->leftHandedFromName->fresh()?->handedness);
    }
}
