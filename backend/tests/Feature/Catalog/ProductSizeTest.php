<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductSize;
use App\Models\Settings\Currency;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del modelo `ProductSize` (Phase 7).
 *
 * Cubre persistencia básica, scope `byProduct`, orden por `position`,
 * y la unique constraint `(product_id, slug)` que garantiza data
 * integrity: dos tallas con el mismo slug dentro del mismo producto
 * son imposibles.
 */
#[Group('catalog')]
#[Group('phase7')]
final class ProductSizeTest extends TestCase
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
    public function it_persists_size_with_slug_label_and_position(): void
    {
        $size = ProductSize::query()->create(attributes: [
            'product_id' => $this->product->id,
            'slug' => '44',
            'label' => '4/4 (Full Size)',
            'position' => 1,
        ]);

        $this->assertDatabaseHas(table: 'product_sizes', data: [
            'id' => $size->id,
            'product_id' => $this->product->id,
            'slug' => '44',
            'label' => '4/4 (Full Size)',
            'position' => 1,
        ]);
    }

    #[Test]
    public function scope_by_product_filters_by_product_id(): void
    {
        $otherProduct = Product::factory()->create([
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'currency_id' => Currency::factory()->primary(),
        ]);

        ProductSize::query()->create(attributes: [
            'product_id' => $this->product->id,
            'slug' => '44',
            'label' => '4/4',
        ]);
        ProductSize::query()->create(attributes: [
            'product_id' => $this->product->id,
            'slug' => '34',
            'label' => '3/4',
        ]);
        ProductSize::query()->create(attributes: [
            'product_id' => $otherProduct->id,
            'slug' => '44',
            'label' => '4/4',
        ]);

        $slugs = ProductSize::query()
            ->byProduct(productId: $this->product->id)
            ->pluck(column: 'slug')
            ->all();

        sort($slugs);
        $this->assertSame(expected: ['34', '44'], actual: $slugs);
    }

    #[Test]
    public function same_slug_within_same_product_is_rejected_by_unique_constraint(): void
    {
        ProductSize::query()->create(attributes: [
            'product_id' => $this->product->id,
            'slug' => '44',
            'label' => '4/4',
        ]);

        $this->expectException(QueryException::class);

        ProductSize::query()->create(attributes: [
            'product_id' => $this->product->id,
            'slug' => '44',
            'label' => 'Duplicate — must fail',
        ]);
    }

    #[Test]
    public function same_slug_in_different_products_is_allowed(): void
    {
        $otherProduct = Product::factory()->create([
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'currency_id' => Currency::factory()->primary(),
        ]);

        ProductSize::query()->create(attributes: [
            'product_id' => $this->product->id,
            'slug' => 'm',
            'label' => 'Medium',
        ]);
        $otherSize = ProductSize::query()->create(attributes: [
            'product_id' => $otherProduct->id,
            'slug' => 'm',
            'label' => 'Medium',
        ]);

        $this->assertSame(expected: 'm', actual: $otherSize->slug);
        $this->assertSame(expected: $otherProduct->id, actual: $otherSize->product_id);
    }
}
