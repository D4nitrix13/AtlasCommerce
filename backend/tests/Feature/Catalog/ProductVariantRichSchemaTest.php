<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del schema rich de variantes (Phase 7).
 *
 * Cubre los nuevos campos fillable (`finish_slug`, `finish_label`,
 * `finish_hex`, `condition`, `stock`, `size_id`) y los scopes
 * asociados (`scopeInStock`, `scopeByFinish`, `scopeByCondition`).
 *
 * No es un test del controller — eso vive en
 * `Admin/ProductVariantControllerTest`. Acá validamos la capa
 * modelo que el seeder y los consumers de storefront dependen.
 */
#[Group('catalog')]
#[Group('phase7')]
final class ProductVariantRichSchemaTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::factory()->create([
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'currency_id' => Currency::factory()->primary(),
        ]);
    }

    #[Test]
    public function it_persists_finish_and_condition_columns(): void
    {
        $variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'finish_slug' => 'rojo',
            'finish_label' => 'Rojo',
            'finish_hex' => '#B91C1C',
            'condition' => 'nuevo',
            'stock' => 5,
        ]);

        $this->assertDatabaseHas(table: 'product_variants', data: [
            'id' => $variant->id,
            'finish_slug' => 'rojo',
            'finish_label' => 'Rojo',
            'finish_hex' => '#B91C1C',
            'condition' => 'nuevo',
            'stock' => 5,
        ]);
    }

    #[Test]
    public function scope_in_stock_returns_only_variants_with_positive_stock(): void
    {
        ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'IN-STOCK-A',
            'stock' => 3,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'OUT-OF-STOCK',
            'stock' => 0,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'IN-STOCK-B',
            'stock' => 10,
        ]);

        $skus = ProductVariant::query()
            ->inStock()
            ->where(column: 'product_id', operator: '=', value: $this->product->id)
            ->pluck(column: 'sku')
            ->all();

        sort($skus);
        $this->assertSame(expected: ['IN-STOCK-A', 'IN-STOCK-B'], actual: $skus);
    }

    #[Test]
    public function scope_by_finish_filters_by_finish_slug(): void
    {
        ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'RED-NEW',
            'finish_slug' => 'rojo',
            'condition' => 'nuevo',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'RED-USED',
            'finish_slug' => 'rojo',
            'condition' => 'usado',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'BLACK-NEW',
            'finish_slug' => 'negro',
            'condition' => 'nuevo',
        ]);

        $redSkus = ProductVariant::query()
            ->byFinish(finishSlug: 'rojo')
            ->pluck(column: 'sku')
            ->all();

        sort($redSkus);
        $this->assertSame(expected: ['RED-NEW', 'RED-USED'], actual: $redSkus);
    }

    #[Test]
    public function scope_by_condition_filters_by_condition(): void
    {
        ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'USED-A',
            'finish_slug' => 'sunburst',
            'condition' => 'usado',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'USED-B',
            'finish_slug' => 'negro',
            'condition' => 'usado',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'NEW-A',
            'finish_slug' => 'sunburst',
            'condition' => 'nuevo',
        ]);

        $usedSkus = ProductVariant::query()
            ->byCondition(condition: 'usado')
            ->pluck(column: 'sku')
            ->all();

        sort($usedSkus);
        $this->assertSame(expected: ['USED-A', 'USED-B'], actual: $usedSkus);
    }

    #[Test]
    public function it_stores_size_id_via_relation(): void
    {
        $size = \App\Models\Catalog\ProductSize::query()->create(attributes: [
            'product_id' => $this->product->id,
            'slug' => '44',
            'label' => '4/4 (Full Size)',
            'position' => 1,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'size_id' => $size->id,
        ]);

        $this->assertSame(expected: $size->id, actual: $variant->size_id);
        $this->assertSame(expected: '4/4 (Full Size)', actual: $variant->size?->label);
    }

    #[Test]
    public function condition_defaults_to_nuevo_at_db_level_when_unspecified(): void
    {
        $variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            // condition omitido — DB default = 'nuevo'.
        ]);

        $this->assertSame(expected: 'nuevo', actual: $variant->condition);
    }
}
