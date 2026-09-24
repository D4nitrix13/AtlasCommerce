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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del endpoint público `GET /api/v1/storefront/products/search`.
 *
 * RF-CAT-028 — Búsqueda pública de productos con ranking por relevancia.
 *
 * Cubre:
 *  - Happy: encuentra por name, sku, description, public_title.
 *  - Ranking: SKU exacto > nombre starts-with > nombre contains.
 *  - Aislamiento: productos de OTRA tienda no se filtran.
 *  - Filtros: solo productos is_active + published_at + is_visible.
 *  - Paginación: respeta page y per_page.
 *  - Validación: q vacío/muy largo/falta → 422.
 *  - Edge: trim de espacios en el query.
 */
#[Group('storefront')]
#[Group('search')]
final class StorefrontProductSearchTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Store $otherStore;

    private Category $category;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::factory()->primary()->create();
        $this->category = Category::factory()->create();
        $this->brand = Brand::factory()->create();

        // Tienda principal donde corre el middleware store.domain.
        $this->store = Store::factory()
            ->for($currency, 'currency')
            ->create(['slug' => 'panda-estampados']);

        StoreDomain::query()->create(attributes: [
            'store_id' => $this->store->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        // Tienda "vecina" para verificar aislamiento.
        $this->otherStore = Store::factory()
            ->for($currency, 'currency')
            ->create(['slug' => 'kitsune']);
    }

    /**
     * Helper: crea un producto publicado y visible en la tienda dada.
     *
     * @param  array<string, mixed>  $attributes  Atributos del Product.
     */
    private function makeProduct(Store $store, array $attributes = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
            'published_at' => Carbon::now()->subDay(),
        ], $attributes));

        // Creamos el row en `product_stores` directamente: la relación
        // belongsToMany del modelo Product apunta a ProductStore, no a
        // Store, y por la forma del pivot la firma `attach()` no aplica
        // acá. Para tests es más simple crear el row explícitamente.
        ProductStore::query()->create(attributes: [
            'product_id' => $product->id,
            'store_id' => $store->id,
            'is_visible' => true,
        ]);

        return $product;
    }

    #[Test]
    public function returns_404_when_store_domain_does_not_resolve(): void
    {
        // Sin StoreDomain que matchee `unknown.test` → 404 por middleware.
        $response = $this->getJson(uri: 'http://unknown.test/api/v1/storefront/products/search?q=camiseta');
        $response->assertStatus(status: 404);
    }

    #[Test]
    public function returns_validation_error_when_query_is_missing(): void
    {
        $response = $this->getJson(uri: $this->url(q: null));
        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['q']);
    }

    #[Test]
    public function returns_validation_error_when_per_page_exceeds_max(): void
    {
        $response = $this->getJson(uri: $this->url(q: 'camiseta', perPage: 999));
        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['per_page']);
    }

    #[Test]
    public function returns_empty_result_for_unknown_term(): void
    {
        $this->makeProduct(store: $this->store, attributes: ['name' => 'Camiseta Roja', 'sku' => 'CR-001']);

        $response = $this->getJson(uri: $this->url(q: 'zapatilla'));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath(path: 'meta.query', expect: 'zapatilla');
    }

    #[Test]
    public function finds_product_by_name_case_insensitive(): void
    {
        $this->makeProduct(store: $this->store, attributes: [
            'name' => 'Camiseta Roja Estampada',
            'sku' => 'CR-001',
        ]);

        $response = $this->getJson(uri: $this->url(q: 'camiseta'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function finds_product_by_sku(): void
    {
        $this->makeProduct(store: $this->store, attributes: [
            'name' => 'Camiseta Roja',
            'sku' => 'CAM-ROJA-XL',
        ]);

        $response = $this->getJson(uri: $this->url(q: 'CAM-ROJA-XL'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function finds_product_by_description(): void
    {
        $this->makeProduct(store: $this->store, attributes: [
            'name' => 'Camiseta',
            'sku' => 'CAM-001',
            'description' => 'Camiseta de algodón 100% con estampado fluorescente',
        ]);

        $response = $this->getJson(uri: $this->url(q: 'algodón'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function finds_product_by_public_title_override(): void
    {
        $product = $this->makeProduct(store: $this->store, attributes: [
            'name' => 'Polera Básica',
            'sku' => 'POL-001',
        ]);
        ProductStore::query()
            ->where(column: 'product_id', operator: '=', value: $product->id)
            ->where(column: 'store_id', operator: '=', value: $this->store->id)
            ->update([
                'public_title' => 'Polera Edición Limitada Navideña',
            ]);

        $response = $this->getJson(uri: $this->url(q: 'Navideña'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function ranks_sku_exact_match_above_name_match(): void
    {
        // SKU match: Búsqueda exacta por SKU.
        $skuMatch = $this->makeProduct(store: $this->store, attributes: [
            'name' => 'Polera Azul',
            'sku' => 'BUSCADO-01',
            'published_at' => Carbon::now()->subDays(days: 5),
        ]);
        // Name match: contiene la palabra en el name.
        $nameMatch = $this->makeProduct(store: $this->store, attributes: [
            'name' => 'Camiseta BUSCADO Premium',
            'sku' => 'OTRO-99',
            'published_at' => Carbon::now()->subDays(days: 1),
        ]);

        $response = $this->getJson(uri: $this->url(q: 'BUSCADO'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $ids = collect($response->json(key: 'data'))->pluck('id')->all();
        $this->assertSame(expected: $skuMatch->id, actual: $ids[0], message: 'SKU match debe rankear primero.');
        $this->assertSame(expected: $nameMatch->id, actual: $ids[1]);
    }

    #[Test]
    public function ranks_name_starts_with_above_name_contains(): void
    {
        $startsWith = $this->makeProduct(store: $this->store, attributes: [
            'name' => 'Camiseta Roja',
            'sku' => 'A-001',
        ]);
        $contains = $this->makeProduct(store: $this->store, attributes: [
            'name' => 'Polera Camiseta Style',
            'sku' => 'B-001',
        ]);

        $response = $this->getJson(uri: $this->url(q: 'Camiseta'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $ids = collect($response->json(key: 'data'))->pluck('id')->all();
        $this->assertSame(expected: $startsWith->id, actual: $ids[0]);
        $this->assertSame(expected: $contains->id, actual: $ids[1]);
    }

    #[Test]
    public function does_not_leak_products_from_other_stores(): void
    {
        // Producto en la tienda VECINA, no debe aparecer.
        $this->makeProduct(store: $this->otherStore, attributes: [
            'name' => 'Camiseta Exclusiva Kitsune',
            'sku' => 'KIT-001',
        ]);
        // Producto en NUESTRA tienda, sí debe aparecer.
        $panda = $this->makeProduct(store: $this->store, attributes: [
            'name' => 'Camiseta Panda',
            'sku' => 'PAN-001',
        ]);

        $response = $this->getJson(uri: $this->url(q: 'Camiseta'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $firstId = (int) $response->json(key: 'data.0.id');
        $this->assertSame(expected: $panda->id, actual: $firstId);
    }

    #[Test]
    public function excludes_inactive_products(): void
    {
        $this->makeProduct(store: $this->store, attributes: [
            'name' => 'Camiseta Activa',
            'sku' => 'A-001',
            'is_active' => false,
        ]);

        $response = $this->getJson(uri: $this->url(q: 'Camiseta'));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function excludes_unpublished_products(): void
    {
        $this->makeProduct(store: $this->store, attributes: [
            'name' => 'Camiseta Borrador',
            'sku' => 'B-001',
            'published_at' => null,
        ]);

        $response = $this->getJson(uri: $this->url(q: 'Camiseta'));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function excludes_products_with_future_published_at(): void
    {
        $this->makeProduct(store: $this->store, attributes: [
            'name' => 'Camiseta Programada',
            'sku' => 'F-001',
            'published_at' => Carbon::now()->addDays(days: 7),
        ]);

        $response = $this->getJson(uri: $this->url(q: 'Camiseta'));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function excludes_products_marked_invisible_in_store(): void
    {
        $product = $this->makeProduct(store: $this->store, attributes: [
            'name' => 'Camiseta Oculta',
            'sku' => 'O-001',
        ]);
        ProductStore::query()
            ->where(column: 'product_id', operator: '=', value: $product->id)
            ->where(column: 'store_id', operator: '=', value: $this->store->id)
            ->update([
                'is_visible' => false,
            ]);

        $response = $this->getJson(uri: $this->url(q: 'Camiseta'));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function paginates_results(): void
    {
        // 7 productos que matchean.
        for ($i = 1; $i <= 7; $i++) {
            $this->makeProduct(store: $this->store, attributes: [
                'name' => "Camiseta Variante {$i}",
                'sku' => sprintf('CV-%03d', $i),
            ]);
        }

        $page1 = $this->getJson(uri: $this->url(q: 'Camiseta', page: 1, perPage: 3));
        $page1->assertOk();
        // El search usa paginación offset (a diferencia del listado de
        // productos que usa cursor — ver RF-PERF-022).
        $page1->assertJsonPath(path: 'meta.per_page', expect: 3);
        $page1->assertJsonPath(path: 'meta.last_page', expect: 3);
        $page1->assertJsonPath(path: 'meta.total', expect: 7);
        $this->assertCount(3, $page1->json(key: 'data'));

        $page3 = $this->getJson(uri: $this->url(q: 'Camiseta', page: 3, perPage: 3));
        $page3->assertJsonPath(path: 'meta.current_page', expect: 3);
        $this->assertCount(1, $page3->json(key: 'data'));
    }

    #[Test]
    public function trims_query_before_searching(): void
    {
        $this->makeProduct(store: $this->store, attributes: ['name' => 'Camiseta Panda', 'sku' => 'P-001']);

        $response = $this->getJson(uri: $this->url(q: '   Camiseta   '));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath(path: 'meta.query', expect: 'Camiseta');
    }

    /**
     * Helper: arma la URL con el dominio de la tienda.
     */
    private function url(?string $q, int $page = 1, int $perPage = 20): string
    {
        $base = 'http://panda-estampados.com/api/v1/storefront/products/search';

        $params = array_filter([
            'q' => $q,
            'page' => $page > 1 ? $page : null,
            'per_page' => $perPage !== 20 ? $perPage : null,
        ], fn ($v): bool => $v !== null);

        return $params === []
            ? $base
            : $base.'?'.http_build_query(data: $params);
    }
}
