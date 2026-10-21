<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Http\Resources\Storefront\StorefrontProductResource;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductVariant;
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
        $this->assertSame('/images/product-placeholder.svg', $resource['imageUrl']);

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

        // Primary gana aunque no sea la primera por sort_order. El Resource
        // devuelve la ruta del proxy de assets, no el storage key crudo.
        $this->assertSame('/api/v1/storefront/assets/images/b.webp', $resource['imageUrl']);
        // Galería ordenada por sort_order ASC.
        $this->assertSame(
            [
                '/api/v1/storefront/assets/images/a.webp',
                '/api/v1/storefront/assets/images/b.webp',
                '/api/v1/storefront/assets/images/c.webp',
            ],
            $resource['images'],
        );
    }

    #[Test]
    public function image_url_falls_back_to_placeholder_when_no_images(): void
    {
        // Producto sin filas en product_images.
        $product = $this->makeProduct();
        $product->load(['images', 'category', 'brand']);

        $resource = (new StorefrontProductResource($product))
            ->resolve(request: $this->app['request']);

        $this->assertSame('/images/product-placeholder.svg', $resource['imageUrl']);
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
        $response->assertJsonPath(path: 'imageUrl', expect: '/images/product-placeholder.svg');
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

    #[Test]
    public function index_filters_by_category_slug_when_provided(): void
    {
        // 2 productos en la categoría guitarras (default de setUp) + 1 en otra.
        $this->makeProduct(attributes: ['slug' => 'guitar-a', 'name' => 'Guitarra A']);
        $this->makeProduct(attributes: ['slug' => 'guitar-b', 'name' => 'Guitarra B']);

        Category::query()->create(attributes: [
            'name' => 'Amplificadores',
            'slug' => 'amps',
        ]);

        $otherCategory = Category::query()->where('slug', 'amps')->firstOrFail();
        $otherProduct = Product::factory()->create([
            'category_id' => $otherCategory->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'slug' => 'amp-z',
            'name' => 'Amplificador Z',
        ]);
        ProductStore::query()->create([
            'product_id' => $otherProduct->id,
            'store_id' => $this->store->id,
            'is_visible' => true,
        ]);

        $response = $this->getJson(uri: 'http://fender-store.test/api/v1/storefront/products?category=guitarras');

        $response->assertOk();
        $items = $response->json();
        $slugs = collect($items)->pluck('slug')->all();
        $this->assertContains('guitar-a', $slugs);
        $this->assertContains('guitar-b', $slugs);
        $this->assertNotContains('amp-z', $slugs);
    }

    #[Test]
    public function index_without_category_param_returns_all_visible_products(): void
    {
        $this->makeProduct(attributes: ['slug' => 'in-category', 'name' => 'En categoría']);

        Category::query()->create(attributes: [
            'name' => 'Amplificadores',
            'slug' => 'amps',
        ]);
        $otherCategory = Category::query()->where('slug', 'amps')->firstOrFail();
        $otherProduct = Product::factory()->create([
            'category_id' => $otherCategory->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'slug' => 'other-category',
            'name' => 'Otra categoría',
        ]);
        ProductStore::query()->create([
            'product_id' => $otherProduct->id,
            'store_id' => $this->store->id,
            'is_visible' => true,
        ]);

        $response = $this->getJson(uri: 'http://fender-store.test/api/v1/storefront/products');

        $response->assertOk();
        $slugs = collect($response->json())->pluck('slug')->all();
        // Sin filtro, el listado expone ambas categorías.
        $this->assertContains('in-category', $slugs);
        $this->assertContains('other-category', $slugs);
    }

    #[Test]
    public function index_returns_empty_array_for_unknown_category_slug(): void
    {
        $this->makeProduct(attributes: ['slug' => 'exists', 'name' => 'Existe']);

        $response = $this->getJson(uri: 'http://fender-store.test/api/v1/storefront/products?category=does-not-exist');

        $response->assertOk();
        // Lista vacía (NO 404): la PDP prefiere empty sobre error al
        // derivar relacionados de una categoría inválida.
        $this->assertSame([], $response->json());
    }

    // ── Galería: una entrada por ROL, no por resolución ────────────────
    //
    // El árbol de assets guarda cada foto en 4 resoluciones y el seeder
    // persiste las 4 como filas de `product_images`. Como el
    // `storage_key` es el SHA-256 del binario, el rol no viaja en la URL
    // pero sí en `sort_order` (1:1 con `CatalogAssetsSeeder::ROLE_ORDER`)
    // y el ancho viaja en `product_images.width`. Sin colapsar por rol, un
    // producto de 8 fotos muestra 32 slides.

    /**
     * Adjunta las 4 resoluciones de un rol (`sort_order` dado).
     */
    private function attachResolutions(Product $product, int $sortOrder, string $role): void
    {
        foreach ([400, 800, 1200, 1600] as $width) {
            $this->attachImage($product, [
                'image_url' => "tenants/fender-store/products/strat/{$role}-{$width}.webp",
                'alt_text' => "Strat - vista {$role}",
                'sort_order' => $sortOrder,
                'width' => $width,
                'is_primary' => $width === 1600 && $sortOrder === 1,
            ]);
        }
    }

    /**
     * Resuelve el Resource con `variants` cargada, que es la señal de
     * que el consumer es el PDP y por lo tanto la shape de `images` es
     * la de objetos agrupados.
     *
     * @return list<array<string, mixed>>
     */
    private function galleryFor(Product $product): array
    {
        $product->load(relations: ['images', 'variants', 'category', 'brand']);

        return (new StorefrontProductResource($product))
            ->resolve(request: $this->app['request'])['images'];
    }

    #[Test]
    public function it_collapses_the_resolutions_of_a_role_into_one_gallery_entry_with_a_srcset(): void
    {
        $product = $this->makeProduct();
        $this->attachResolutions($product, sortOrder: 1, role: 'front');

        $images = $this->galleryFor($product);

        $this->assertCount(1, $images);
        // El slide principal es la de MAYOR resolución.
        $this->assertSame(
            expected: '/api/v1/storefront/assets/tenants/fender-store/products/strat/front-1600.webp',
            actual: $images[0]['url'],
        );
        $this->assertTrue($images[0]['isPrimary']);
        // Las otras tres NO se pierden: quedan en el srcset, de menor a
        // mayor, con la URL del proxy de CADA resolución.
        $this->assertSame(
            expected: '/api/v1/storefront/assets/tenants/fender-store/products/strat/front-400.webp 400w, '
                .'/api/v1/storefront/assets/tenants/fender-store/products/strat/front-800.webp 800w, '
                .'/api/v1/storefront/assets/tenants/fender-store/products/strat/front-1200.webp 1200w, '
                .'/api/v1/storefront/assets/tenants/fender-store/products/strat/front-1600.webp 1600w',
            actual: $images[0]['srcset'],
        );
    }

    #[Test]
    public function it_emits_one_entry_per_role_in_gallery_order(): void
    {
        $product = $this->makeProduct();
        $this->attachResolutions($product, sortOrder: 1, role: 'front');
        $this->attachResolutions($product, sortOrder: 2, role: 'back');
        $this->attachResolutions($product, sortOrder: 3, role: 'body');

        $images = $this->galleryFor($product);

        $this->assertCount(3, $images);
        $this->assertSame(expected: [1, 2, 3], actual: array_column($images, 'sortOrder'));
    }

    #[Test]
    public function it_does_not_collapse_a_role_whose_resolutions_are_unknown(): void
    {
        // Filas heredadas de un seed previo: sin `width` no se sabe cuál
        // es la mayor resolución. Elegir una al azar pondría un thumbnail
        // de 400px como slide principal, así que el grupo se deja como
        // estaba en vez de inventar un orden.
        $product = $this->makeProduct();
        $this->attachImage($product, ['image_url' => 'a-1.webp', 'sort_order' => 1, 'width' => null]);
        $this->attachImage($product, ['image_url' => 'a-2.webp', 'sort_order' => 1, 'width' => null]);
        $this->attachImage($product, ['image_url' => 'b-1.webp', 'sort_order' => 2, 'width' => null]);

        $images = $this->galleryFor($product);

        $this->assertCount(3, $images);
        $this->assertSame(expected: '', actual: $images[0]['srcset']);
    }

    #[Test]
    public function the_flat_url_branch_of_images_stays_ungrouped(): void
    {
        // Rama del Resource sin `variants` eager-loaded: `images` sigue
        // siendo la lista plana de URLs, sin agrupar ni srcset. Ese
        // shape es el que consumen los callers que sólo quieren URLs.
        //
        // OJO: hoy `ProductController` eager-loadea `variants` en `index()`
        // igual que en `show()`, así que en HTTP el listado cae en la
        // rama de objetos. Esta test cubre la rama, no la ruta.
        $product = $this->makeProduct();
        $this->attachResolutions($product, sortOrder: 1, role: 'front');
        $this->attachResolutions($product, sortOrder: 2, role: 'back');

        $product->load(relations: ['images', 'category', 'brand']);
        $resource = (new StorefrontProductResource($product))->resolve(request: $this->app['request']);

        $this->assertCount(8, $resource['images']);
        foreach ($resource['images'] as $url) {
            $this->assertIsString($url);
        }
    }

    #[Test]
    public function it_does_not_collapse_the_same_role_across_two_finishes(): void
    {
        // `sort_order` es el RANK del rol y se repite en cada acabado:
        // los seis finishes de `sg-standard` tienen su `front` en 1.
        // Agrupar sólo por rol dejaría la galería con las fotos de UN
        // acabado y el filtro `?finish=` del PDP dejaría de filtrar.
        $product = $this->makeProduct();
        $variantA = ProductVariant::query()->create(attributes: [
            'product_id' => $product->id,
            'name' => 'Cardinal Red Burst',
            'sku' => 'SG-CARDINAL',
            'price_cents' => 150000,
            'is_active' => true,
            'finish_slug' => 'cardinal-red-burst',
            'finish_label' => 'Cardinal Red Burst',
            'condition' => 'nuevo',
            'stock' => 0,
        ]);
        $variantB = ProductVariant::query()->create(attributes: [
            'product_id' => $product->id,
            'name' => 'Ebony',
            'sku' => 'SG-EBONY',
            'price_cents' => 150000,
            'is_active' => true,
            'finish_slug' => 'ebony',
            'finish_label' => 'Ebony',
            'condition' => 'nuevo',
            'stock' => 0,
        ]);

        foreach ([400, 800, 1200, 1600] as $width) {
            $this->attachImage($product, [
                'image_url' => "tenants/fender-store/products/strat/cardinal-front-{$width}.webp",
                'sort_order' => 1,
                'variant_id' => $variantA->id,
                'width' => $width,
                'is_primary' => $width === 1600,
            ]);
            $this->attachImage($product, [
                'image_url' => "tenants/fender-store/products/strat/ebony-front-{$width}.webp",
                'sort_order' => 1,
                'variant_id' => $variantB->id,
                'width' => $width,
                'is_primary' => $width === 1600,
            ]);
        }

        $images = $this->galleryFor($product);

        // Dos entradas, una por acabado — no una sola con 8 candidatas.
        $this->assertCount(2, $images);
        $this->assertSame(
            expected: [$variantA->id, $variantB->id],
            actual: array_column($images, 'variantId'),
        );
        $this->assertSame(
            expected: '/api/v1/storefront/assets/tenants/fender-store/products/strat/cardinal-front-1600.webp',
            actual: $images[0]['url'],
        );
        $this->assertSame(
            expected: '/api/v1/storefront/assets/tenants/fender-store/products/strat/ebony-front-1600.webp',
            actual: $images[1]['url'],
        );
    }
}
