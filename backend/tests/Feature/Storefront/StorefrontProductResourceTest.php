<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Http\Resources\Storefront\StorefrontProductResource;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Settings\Currency;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del Resource `StorefrontProductResource` y del endpoint
 * `GET /api/v1/storefront/products/{slug}`.
 *
 * Cubre la conversión del modelo `Product` a la shape que consume
 * el frontend PDP / listado (interface `Product` en
 * `frontend/src/types/catalog.ts`):
 *
 *  - claves camelCase presentes: `priceCents`, `imageUrl`, `category`,
 *    `brand`, `hasCondition`, `hasSizes`.
 *  - claves opcionales omitidas según contrato: `images`, `isExclusive`.
 *  - `imageUrl` cae al placeholder cuando no hay imágenes en BD.
 *  - `category` es `categories.slug`; `brand` es `brands.name`.
 *  - El listado no se rompe: `index()` devuelve array plano, no
 *    `{data: [...]}`, conservando el contrato del componente
 *    `StorefrontProductsList.tsx`.
 *  - `show()` devuelve 200 con shape correcta y 404 con slug inexistente.
 */
#[Group('storefront')]
#[Group('products')]
final class StorefrontProductResourceTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Category $category;

    private Brand $brand;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::factory()->primary()->create();
        $this->category = Category::factory()->create(attributes: [
            'name' => 'Guitarras',
            'slug' => 'guitarras',
        ]);
        $this->brand = Brand::factory()->create(attributes: [
            'name' => 'Fender',
            'slug' => 'fender',
        ]);

        $this->store = Store::factory()
            ->for($this->currency, 'currency')
            ->create(['slug' => 'fender-store']);

        StoreDomain::query()->create(attributes: [
            'store_id' => $this->store->id,
            'domain' => 'fender-store.test',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Helper: crea un producto publicado y visible en la tienda.
     *
     * @param  array<string, mixed>  $attributes  Overrides del Product.
     */
    private function makeProduct(array $attributes = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'name' => 'Stratocaster American Pro',
            'slug' => 'stratocaster-american-pro',
            'description' => 'Guitarra eléctrica Stratocaster American Pro.',
            'base_price_cents' => 150_000,
            'is_active' => true,
            'has_condition' => true,
            'has_sizes' => false,
            'is_exclusive' => false,
        ], $attributes));

        ProductStore::query()->create(attributes: [
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'is_visible' => true,
        ]);

        return $product;
    }

    /**
     * Crea una `product_image` para el producto.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function attachImage(Product $product, array $attributes = []): ProductImage
    {
        return ProductImage::query()->create(array_merge([
            'product_id' => $product->id,
            'image_url' => 'tenants/fender-store/products/strat/front.webp',
            'alt_text' => 'Vista frontal',
            'sort_order' => 1,
            'is_primary' => false,
        ], $attributes));
    }

    #[Test]
    public function resource_emits_required_camelcase_fields_and_omits_optionals_when_absent(): void
    {
        $product = $this->makeProduct();
        $product->load(['category', 'brand', 'images']);

        $resource = (new StorefrontProductResource($product))
            ->resolve(request: $this->app['request']);

        // Requeridos por la interface Product del frontend.
        $this->assertSame($product->id, $resource['id']);
        $this->assertSame($product->slug, $resource['slug']);
        $this->assertSame($product->name, $resource['name']);
        $this->assertSame($product->description, $resource['description']);
        $this->assertSame(150_000, $resource['priceCents']);
        $this->assertSame('guitarras', $resource['category']);

        // imageUrl siempre presente (placeholder si no hay imágenes).
        $this->assertArrayHasKey('imageUrl', $resource);
        $this->assertSame('/images/product-placeholder.png', $resource['imageUrl']);

        // Flags siempre presentes.
        $this->assertTrue($resource['hasCondition']);
        $this->assertFalse($resource['hasSizes']);

        // brand emitido porque está cargado y no es null.
        $this->assertSame('Fender', $resource['brand']);

        // Opcionales ausentes: images (porque relación no cargada),
        // isExclusive (porque false), currency (no cargado).
        $this->assertArrayNotHasKey('images', $resource);
        $this->assertArrayNotHasKey('isExclusive', $resource);
        $this->assertArrayNotHasKey('currency', $resource);
    }

    #[Test]
    public function image_url_prefers_primary_image_over_sorted_fallback(): void
    {
        // 3 imágenes: la del medio (sort=2) es la marcada como primary.
        $product = $this->makeProduct();

        $this->attachImage($product, [
            'image_url' => 'images/a.webp',
            'sort_order' => 1,
            'is_primary' => false,
        ]);
        $this->attachImage($product, [
            'image_url' => 'images/b.webp',
            'sort_order' => 2,
            'is_primary' => true,
        ]);
        $this->attachImage($product, [
            'image_url' => 'images/c.webp',
            'sort_order' => 3,
            'is_primary' => false,
        ]);

        $product->refresh();
        $product->load(['images', 'category', 'brand']);

        $resource = (new StorefrontProductResource($product))
            ->resolve(request: $this->app['request']);

        // Primary gana aunque no sea la primera por sort_order.
        $this->assertSame('images/b.webp', $resource['imageUrl']);
        // Galería ordenada por sort_order ASC.
        $this->assertSame(['images/a.webp', 'images/b.webp', 'images/c.webp'], $resource['images']);
    }

    #[Test]
    public function image_url_falls_back_to_placeholder_when_no_images(): void
    {
        // Producto sin filas en product_images.
        $product = $this->makeProduct();
        $product->load(['images', 'category', 'brand']);

        $resource = (new StorefrontProductResource($product))
            ->resolve(request: $this->app['request']);

        $this->assertSame('/images/product-placeholder.png', $resource['imageUrl']);
        $this->assertArrayNotHasKey('images', $resource);
    }

    #[Test]
    public function is_exclusive_emitted_only_when_true(): void
    {
        $product = $this->makeProduct(attributes: ['is_exclusive' => true]);
        $product->load(['category', 'brand', 'images']);

        $resource = (new StorefrontProductResource($product))
            ->resolve(request: $this->app['request']);

        $this->assertTrue($resource['isExclusive']);
    }

    #[Test]
    public function show_endpoint_returns_resource_shape(): void
    {
        $product = $this->makeProduct();

        $response = $this->getJson(uri: 'http://fender-store.test/api/v1/storefront/products/stratocaster-american-pro');

        $response->assertOk();
        // Shape plana (sin `{data: {...}}`).
        $response->assertJsonPath(path: 'id', expect: $product->id);
        $response->assertJsonPath(path: 'slug', expect: 'stratocaster-american-pro');
        $response->assertJsonPath(path: 'priceCents', expect: 150_000);
        $response->assertJsonPath(path: 'category', expect: 'guitarras');
        $response->assertJsonPath(path: 'brand', expect: 'Fender');
        $response->assertJsonPath(path: 'imageUrl', expect: '/images/product-placeholder.png');
        // No envoltura "data".
        $response->assertJsonMissingPath(path: 'data.id');
    }

    #[Test]
    public function show_endpoint_returns_404_for_unknown_slug(): void
    {
        $this->makeProduct(attributes: ['slug' => 'existing-product']);

        $response = $this->getJson(uri: 'http://fender-store.test/api/v1/storefront/products/non-existent-slug-zzz');

        $response->assertStatus(status: 404);
    }

    #[Test]
    public function index_endpoint_returns_flat_array_with_resource_shape(): void
    {
        // Crea 3 productos en la tienda activa.
        $this->makeProduct(attributes: ['slug' => 'product-a', 'name' => 'Producto A']);
        $this->makeProduct(attributes: ['slug' => 'product-b', 'name' => 'Producto B']);
        $this->makeProduct(attributes: ['slug' => 'product-c', 'name' => 'Producto C']);

        $response = $this->getJson(uri: 'http://fender-store.test/api/v1/storefront/products');

        $response->assertOk();
        $items = $response->json();

        // Flat array, no `{data: [...]}`.
        $this->assertIsArray($items);
        $this->assertCount(3, $items);
        $this->assertArrayHasKey('priceCents', $items[0]);
        $this->assertArrayHasKey('category', $items[0]);
    }

    #[Test]
    public function index_endpoint_does_not_leak_products_from_other_stores(): void
    {
        // Producto en la tienda activa.
        $this->makeProduct(attributes: ['slug' => 'mine', 'name' => 'Mio']);

        // Tienda vecina, producto que NO debe aparecer.
        $otherStore = Store::factory()
            ->for($this->currency, 'currency')
            ->create(['slug' => 'other']);
        $otherProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'name' => 'Ajeno',
            'slug' => 'theirs',
        ]);
        ProductStore::query()->create([
            'product_id' => $otherProduct->id,
            'store_id' => $otherStore->id,
            'is_visible' => true,
        ]);

        $response = $this->getJson(uri: 'http://fender-store.test/api/v1/storefront/products');

        $response->assertOk();
        $slugs = collect($response->json())->pluck('slug')->all();
        $this->assertContains('mine', $slugs);
        $this->assertNotContains('theirs', $slugs);
    }
}
