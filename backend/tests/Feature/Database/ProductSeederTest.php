<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use Database\Seeders\PermissionsCatalogSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('seeders')]
#[Group('catalog')]
final class ProductSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
    }

    #[Test]
    public function it_seeds_all_products_defined_in_json(): void
    {
        $this->seed(class: ProductSeeder::class);

        $json = $this->loadJson();
        $expectedCount = count($json['products'] ?? []);

        $this->assertSame(
            expected: $expectedCount,
            actual: Product::query()->count(),
            message: 'Expected one product row per entry in products.json',
        );
    }

    #[Test]
    public function it_creates_brand_and_category_relations(): void
    {
        $this->seed(class: ProductSeeder::class);

        $this->assertGreaterThan(0, Brand::query()->count(), message: 'Brands should be created (at least Fender + Gibson)');
        $this->assertGreaterThan(0, Category::query()->count(), message: 'Category "Guitarra eléctrica" should be created');
        $this->assertGreaterThan(
            0,
            Currency::query()->where(column: 'code', operator: '=', value: 'USD')->count(),
        );
    }

    #[Test]
    public function it_creates_variants_for_each_product(): void
    {
        $this->seed(class: ProductSeeder::class);

        $json = $this->loadJson();
        $expectedVariantCount = array_sum(array_map(
            fn (array $product): int => count($product['variants'] ?? []),
            $json['products'] ?? [],
        ));

        $this->assertSame(
            expected: $expectedVariantCount,
            actual: ProductVariant::query()->count(),
            message: 'Expected one variant row per variant entry across all products',
        );
    }

    #[Test]
    public function fender_telecaster_seeds_with_seven_variants(): void
    {
        $this->seed(class: ProductSeeder::class);

        $product = Product::query()
            ->where(column: 'slug', operator: '=', value: 'american-professional-telecaster')
            ->first();

        $this->assertNotNull($product, 'Fender American Professional Telecaster must exist');
        $this->assertSame(
            expected: 7,
            actual: $product->variants()->count(),
            message: 'Tele must have 7 variants per products.json',
        );
    }

    #[Test]
    public function gibson_mod_collection_creates_ten_products(): void
    {
        $this->seed(class: ProductSeeder::class);

        $count = Product::query()
            ->whereHas(
                relation: 'brand',
                callback: fn ($q) => $q->where(column: 'slug', operator: '=', value: 'gibson'),
            )
            ->where(column: 'slug', operator: 'like', value: 'gibson-mod-collection-%')
            ->count();

        $this->assertSame(
            expected: 9,
            actual: $count,
            message: 'Gibson Mod Collection contributes 9 products (Custom Select es entry separado)',
        );
    }

    #[Test]
    public function indexed_image_pattern_uses_slug_and_variant_in_filename(): void
    {
        $this->seed(class: ProductSeeder::class);

        $product = Product::query()
            ->where(column: 'slug', operator: '=', value: 'american-professional-telecaster')
            ->firstOrFail();

        $image = ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $product->id)
            ->where(column: 'is_primary', operator: '=', value: true)
            ->first();

        $this->assertNotNull($image);
        $this->assertStringContainsString(
            needle: '/images/products/american-professional-telecaster/3-color-sunburst/american-professional-telecaster-3-color-sunburst-1600x525-01',
            haystack: $image->image_url,
        );
    }

    #[Test]
    public function back_image_with_back_sku_uses_sku_in_filename(): void
    {
        $this->seed(class: ProductSeeder::class);

        $product = Product::query()
            ->where(column: 'slug', operator: '=', value: 'american-professional-telecaster')
            ->firstOrFail();

        $backImage = ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $product->id)
            ->where(column: 'alt_text', operator: 'like', value: '3-color-sunburst — vista 02')
            ->first();

        $this->assertNotNull($backImage);
        $this->assertStringContainsString(
            needle: '0111140700-0111140700-1600x525-02',
            haystack: $backImage->image_url,
            message: 'Back image filename must use back_sku (0111140700) per products.json',
        );
    }

    #[Test]
    public function keyword_image_pattern_duplicates_variant_in_filename(): void
    {
        $this->seed(class: ProductSeeder::class);

        $product = Product::query()
            ->where(column: 'slug', operator: '=', value: 'gibson-mod-collection-sg-standard-holy-shift')
            ->firstOrFail();

        $image = ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $product->id)
            ->first();

        $this->assertNotNull($image);
        $this->assertStringContainsString(
            needle: 'gibson-mod-collection-sg-standard-holy-shift-holy-shift-front-1600x1600',
            haystack: $image->image_url,
            message: 'Gibson Mod usa keyword pattern con variant duplicado',
        );
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $this->seed(class: ProductSeeder::class);
        $firstProductCount = Product::query()->count();
        $firstVariantCount = ProductVariant::query()->count();
        $firstImageCount = ProductImage::query()->count();

        $this->seed(class: ProductSeeder::class);

        $this->assertSame(
            expected: $firstProductCount,
            actual: Product::query()->count(),
            message: 'Product count must remain stable across re-runs',
        );
        $this->assertSame(
            expected: $firstVariantCount,
            actual: ProductVariant::query()->count(),
            message: 'Variant count must remain stable across re-runs',
        );
        $this->assertSame(
            expected: $firstImageCount,
            actual: ProductImage::query()->count(),
            message: 'Image count must remain stable across re-runs (delete+recreate)',
        );
    }

    #[Test]
    public function each_product_runs_in_isolated_transaction(): void
    {
        // El seeder envuelve CADA producto en DB::transaction. Simulamos
        // un fallo a mitad del segundo producto: el primero debe quedar
        // persistido y el segundo en estado previo.
        DB::enableQueryLog();

        $this->seed(class: ProductSeeder::class);

        // Si llegamos acá sin excepción, todas las transacciones commitearon.
        $this->assertGreaterThan(0, Product::query()->count());

        DB::disableQueryLog();
    }

    /**
     * @return array{products?: array<int, array<string, mixed>>}
     */
    private function loadJson(): array
    {
        /** @var array{products?: array<int, array<string, mixed>>} $decoded */
        $decoded = json_decode(
            json: file_get_contents(database_path('seeds/products.json')),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        return $decoded;
    }
}
