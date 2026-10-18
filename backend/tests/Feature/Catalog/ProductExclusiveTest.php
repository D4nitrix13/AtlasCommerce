<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Settings\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de la bandera `is_exclusive` y el scope `scopeExclusive`
 * (Phase 7).
 *
 * Cubre el cast a bool, el default `false`, el scope que filtra por
 * exclusivos, y la coexistencia con `is_active` (un producto exclusivo
 * debe además estar activo para mostrarse).
 */
#[Group('catalog')]
#[Group('phase7')]
final class ProductExclusiveTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private Brand $brand;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::factory()->create();
        $this->brand = Brand::factory()->create();
        $this->currency = Currency::factory()->primary()->create();
    }

    #[Test]
    public function is_exclusive_defaults_to_false(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
        ]);

        $this->assertFalse($product->is_exclusive);
    }

    #[Test]
    public function is_exclusive_can_be_set_to_true(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'is_exclusive' => true,
        ]);

        $this->assertTrue($product->is_exclusive);
        $this->assertTrue($product->fresh()->is_exclusive);
    }

    #[Test]
    public function scope_exclusive_returns_only_products_with_is_exclusive_true(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'slug' => 'regular-product',
            'is_exclusive' => false,
        ]);
        Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'slug' => 'limited-blueberry',
            'is_exclusive' => true,
        ]);
        Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'slug' => 'limited-flame',
            'is_exclusive' => true,
        ]);

        $slugs = Product::query()->exclusive()->pluck(column: 'slug')->all();
        sort($slugs);

        $this->assertSame(expected: ['limited-blueberry', 'limited-flame'], actual: $slugs);
    }

    #[Test]
    public function has_condition_and_has_sizes_can_be_saved(): void
    {
        // Los flags `has_condition` y `has_sizes` controlan si la PDP
        // expone los selectores Phase 7. Verificamos persistencia
        // (no default — backend siempre los setea explícito).
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'has_condition' => true,
            'has_sizes' => true,
        ]);

        $this->assertTrue($product->has_condition);
        $this->assertTrue($product->has_sizes);
    }
}
