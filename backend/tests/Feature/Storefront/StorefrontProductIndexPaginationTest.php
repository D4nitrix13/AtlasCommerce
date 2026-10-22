<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Settings\Currency;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Services\Catalog\ProductSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de paginación de `GET /api/v1/storefront/products`
 * (`StorefrontProductController@index`).
 *
 * El endpoint pasó de devolver el catálogo entero del tenant (346 filas /
 * ~2.3 MB para un grid de 24 cards, con `?page=` y `?per_page=` ignorados)
 * a devolver una página con la misma envoltura que `search()`:
 * `{data, meta}`.
 *
 * Cubre:
 *  - Envoltura `{data, meta}` + default de `per_page`.
 *  - `?page=N` devuelve la página N real (y sin filas repetidas entre
 *    páginas — depende del ORDER BY determinista).
 *  - `?page=` fuera de rango: `data` vacío pero `meta` con el total real.
 *  - Validación: `page` no entero / `per_page` > MAX → 422.
 *  - Aislamiento por tenant (el `meta.total` también lo respeta).
 */
#[Group('storefront')]
#[Group('products')]
final class StorefrontProductIndexPaginationTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Store $otherStore;

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

        // Tienda "vecina" para verificar que el `total` también aísla.
        $this->otherStore = Store::factory()
            ->for($this->currency, 'currency')
            ->create(['slug' => 'other-store']);
    }

    #[Test]
    public function returns_paginated_envelope_on_default_page(): void
    {
        $this->makeVisibleProducts(count: 3);

        $response = $this->getJson(uri: $this->url());

        $response->assertOk();
        $response->assertJsonStructure(['data', 'meta' => [
            'current_page', 'per_page', 'total', 'last_page',
        ]]);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonPath(path: 'meta.current_page', expect: 1);
        $response->assertJsonPath(path: 'meta.total', expect: 3);
        $response->assertJsonPath(path: 'meta.last_page', expect: 1);
    }

    #[Test]
    public function falls_back_to_search_service_default_per_page_when_param_is_absent(): void
    {
        // 25 productos > DEFAULT_PER_PAGE (20): sin `per_page` explícito
        // la respuesta debe traer 20 filas, NO las 25 del catálogo.
        $this->makeVisibleProducts(count: 25);

        $response = $this->getJson(uri: $this->url());

        $response->assertOk();
        $response->assertJsonCount(ProductSearchService::DEFAULT_PER_PAGE, 'data');
        $response->assertJsonPath(path: 'meta.per_page', expect: ProductSearchService::DEFAULT_PER_PAGE);
        $response->assertJsonPath(path: 'meta.total', expect: 25);
        $response->assertJsonPath(path: 'meta.last_page', expect: 2);
    }

    #[Test]
    public function returns_second_page_when_page_is_two(): void
    {
        $this->makeVisibleProducts(count: 7);

        $firstPage = $this->getJson(uri: $this->url(page: 1, perPage: 3));
        $firstPage->assertOk();
        $firstPage->assertJsonCount(3, 'data');
        $firstPage->assertJsonPath(path: 'meta.current_page', expect: 1);
        $firstPage->assertJsonPath(path: 'meta.last_page', expect: 3);

        $secondPage = $this->getJson(uri: $this->url(page: 2, perPage: 3));
        $secondPage->assertOk();
        $secondPage->assertJsonCount(3, 'data');
        $secondPage->assertJsonPath(path: 'meta.current_page', expect: 2);

        // La segunda página trae filas DISTINTAS a la primera: es la
        // garantía de que el ORDER BY (id) hace estable el corte offset.
        $firstIds = collect($firstPage->json(key: 'data'))->pluck('id')->all();
        $secondIds = collect($secondPage->json(key: 'data'))->pluck('id')->all();
        $this->assertSame(
            expected: [],
            actual: array_values(array_intersect($firstIds, $secondIds)),
            message: 'La paginación offset no puede repetir filas entre páginas.',
        );

        // Y la tercera página cierra el catálogo sin huecos ni repetidos.
        $thirdPage = $this->getJson(uri: $this->url(page: 3, perPage: 3));
        $thirdPage->assertJsonCount(1, 'data');
        $allIds = [...$firstIds, ...$secondIds, ...collect($thirdPage->json(key: 'data'))->pluck('id')->all()];
        $this->assertCount(7, array_unique($allIds));
    }

    #[Test]
    public function returns_empty_data_with_real_meta_when_page_is_past_the_end(): void
    {
        $this->makeVisibleProducts(count: 7);

        $response = $this->getJson(uri: $this->url(page: 9999, perPage: 3));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        // El frontend necesita el `last_page` real para clampear
        // `?page=9999` en vez de renderizar un grid vacío.
        $response->assertJsonPath(path: 'meta.current_page', expect: 9999);
        $response->assertJsonPath(path: 'meta.total', expect: 7);
        $response->assertJsonPath(path: 'meta.last_page', expect: 3);
    }

    #[Test]
    public function reports_last_page_consistent_with_total_and_per_page(): void
    {
        $this->makeVisibleProducts(count: 7);

        $response = $this->getJson(uri: $this->url(perPage: 3));

        $response->assertOk();
        $meta = $response->json(key: 'meta');
        $this->assertSame(
            expected: (int) ceil($meta['total'] / $meta['per_page']),
            actual: $meta['last_page'],
        );
        // El listado no emite `query`: sólo `search()` lo hace.
        $this->assertArrayNotHasKey('query', $meta);
    }

    #[Test]
    public function returns_validation_error_when_per_page_exceeds_max(): void
    {
        $response = $this->getJson(uri: $this->url(perPage: 999));

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['per_page']);
    }

    #[Test]
    public function returns_validation_error_when_per_page_is_zero(): void
    {
        $response = $this->getJson(uri: $this->rawUrl(query: 'per_page=0'));

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['per_page']);
    }

    #[Test]
    public function returns_validation_error_when_page_is_not_an_integer(): void
    {
        $response = $this->getJson(uri: $this->rawUrl(query: 'page=abc'));

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['page']);
    }

    #[Test]
    public function returns_validation_error_when_page_is_zero(): void
    {
        $response = $this->getJson(uri: $this->rawUrl(query: 'page=0'));

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['page']);
    }

    #[Test]
    public function meta_total_excludes_products_from_other_stores(): void
    {
        $this->makeVisibleProducts(count: 4);
        $this->makeVisibleProducts(count: 9, store: $this->otherStore);

        $response = $this->getJson(uri: $this->url());

        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 4);
        $response->assertJsonCount(4, 'data');
    }

    #[Test]
    public function meta_total_counts_only_products_of_the_requested_category(): void
    {
        $this->makeVisibleProducts(count: 3);

        $otherCategory = Category::factory()->create(attributes: [
            'name' => 'Amplificadores',
            'slug' => 'amps',
        ]);
        $ampsProduct = Product::factory()->create(array_merge([
            'category_id' => $otherCategory->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'slug' => 'amp-z',
            'name' => 'Amplificador Z',
        ]));
        ProductStore::query()->create(attributes: [
            'product_id' => $ampsProduct->id,
            'store_id' => $this->store->id,
            'is_visible' => true,
        ]);

        $response = $this->getJson(uri: $this->url(category: 'guitarras'));

        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 3);
        $this->assertNotContains(
            'amp-z',
            collect($response->json(key: 'data'))->pluck('slug')->all(),
        );
    }

    /**
     * Helper: crea N productos publicados y visibles en la tienda dada.
     *
     * @param  array<string, mixed>  $attributes  Overrides del Product.
     */
    private function makeVisibleProducts(
        int $count,
        ?Store $store = null,
        array $attributes = [],
    ): void {
        $targetStore = $store ?? $this->store;

        for ($i = 1; $i <= $count; $i++) {
            $product = Product::factory()->create(array_merge([
                'category_id' => $this->category->id,
                'brand_id' => $this->brand->id,
                'currency_id' => $this->currency->id,
                'name' => "Producto {$i}",
                // El slug es único en la tabla: el store entra en el slug
                // para poder crear el mismo set en dos tiendas.
                'slug' => sprintf('%s-producto-%03d', $targetStore->slug, $i),
            ], $attributes));

            ProductStore::query()->create(attributes: [
                'product_id' => $product->id,
                'store_id' => $targetStore->id,
                'is_visible' => true,
            ]);
        }
    }

    /**
     * Helper: arma la URL del listado con el dominio de la tienda.
     */
    private function url(?int $page = null, ?int $perPage = null, ?string $category = null): string
    {
        $params = array_filter([
            'page' => $page,
            'per_page' => $perPage,
            'category' => $category,
        ], fn ($value): bool => $value !== null);

        $base = 'http://fender-store.test/api/v1/storefront/products';

        return $params === []
            ? $base
            : $base.'?'.http_build_query(data: $params);
    }

    /**
     * Helper: URL con query string cruda, para los casos de validación
     * donde el valor NO es un int (el `url()` tipado no los puede armar).
     */
    private function rawUrl(string $query): string
    {
        return 'http://fender-store.test/api/v1/storefront/products?'.$query;
    }
}
