<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Http\Resources\Storefront\StorefrontProductResource;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Settings\Currency;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Invariante de visibilidad de variantes en el storefront.
 *
 * `StorefrontProductResource::resolveVariants()` descarta toda variante
 * sin `finish_slug`. Ese filtro es el que vuelve peligroso al API admin:
 * una variante creada sin acabado no aparece en la PDP, no aparece en el
 * `stock` que el Resource suma, y ningún log ni exception lo delata.
 *
 * Estos tests lo cubren en las dos direcciones:
 *
 *  - Categoría FINISHABLE (`guitars`): el API admin no puede crear ni
 *    dejar sin acabado una variante, así que toda variante que sale de
 *    la API es visible en el storefront con su acabado.
 *  - Categoría NO FINISHABLE (`capos`): el acabado es opcional por
 *    dominio, y una variante sin acabado NO aparece en `variants`. Es el
 *    comportamiento DOCUMENTADO que el filtro del Resource define hoy —
 *    se afirma explícitamente para que un cambio futuro lo tenga que
 *    decidir a propósito.
 *
 * La tienda tiene dos dominios (`admin.test` y `shop.test`) porque el
 * mismo test cruza la escritura por la API admin y la lectura por el
 * endpoint público.
 */
#[Group('storefront')]
#[Group('products')]
#[Group('catalog')]
final class StorefrontVariantVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_BASE = 'http://admin.test/api/v1/admin/variants';

    private const STOREFRONT_BASE = 'http://shop.test/api/v1/storefront/products';

    private User $admin;

    private Store $store;

    private Currency $currency;

    private Brand $brand;

    private Category $guitars;

    private Category $capos;

    private Product $guitarProduct;

    private Product $capoProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);

        $this->currency = Currency::factory()->primary()->create();
        $this->brand = Brand::factory()->create();
        $this->store = Store::factory()->for($this->currency, 'currency')->create([
            'slug' => 'la-voz',
        ]);

        foreach ([['admin.test', true], ['shop.test', false]] as [$domain, $isPrimary]) {
            StoreDomain::query()->create(attributes: [
                'store_id' => $this->store->id,
                'domain' => $domain,
                'type' => 'domain',
                'is_primary' => $isPrimary,
                'is_active' => true,
            ]);
        }

        $this->guitars = Category::factory()->create(attributes: [
            'name' => 'Guitarras',
            'slug' => 'guitars',
        ]);
        $this->capos = Category::factory()->create(attributes: [
            'name' => 'Capos',
            'slug' => 'capos',
        ]);

        $this->admin = User::factory()->create([
            'email' => 'admin@la-voz.test',
            'is_active' => true,
            'role_id' => Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail()->id,
        ]);
        $this->admin->stores()->syncWithoutDetaching(ids: [$this->store->id]);

        $this->guitarProduct = $this->makeProduct(category: $this->guitars, slug: 'les-paul-standard-reissue');
        $this->capoProduct = $this->makeProduct(category: $this->capos, slug: 'maestro-capo');

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);
    }

    private function makeProduct(Category $category, string $slug): Product
    {
        $product = Product::factory()->create(attributes: [
            'category_id' => $category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'slug' => $slug,
            'name' => ucfirst($slug),
            'has_condition' => true,
            'is_active' => true,
            'published_at' => now(),
        ]);

        ProductStore::query()->create(attributes: [
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'is_visible' => true,
        ]);

        return $product;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function storefrontVariants(Product $product): array
    {
        $response = $this->getJson(uri: self::STOREFRONT_BASE.'/'.$product->slug);

        $response->assertOk();

        return $response->json(key: 'variants');
    }

    #[Test]
    public function a_variant_created_through_the_admin_api_shows_up_in_the_storefront_with_its_finish(): void
    {
        $created = $this->postJson(uri: self::ADMIN_BASE, data: [
            'product_id' => $this->guitarProduct->id,
            'name' => 'Iced Tea Burst',
            'sku' => 'LP-ITB-01',
            'price_cents' => 249_900,
            'finish_slug' => 'iced-tea-burst',
            'finish_label' => 'Iced Tea Burst',
            'finish_hex' => '#C67A3D',
            'condition' => 'nuevo',
            'stock' => 2,
            'handedness' => 'left-handed',
        ]);

        $created->assertCreated();
        $variantId = (int) $created->json(key: 'data.id');

        // (a) Por el endpoint público: el contrato que consume la PDP.
        $variants = $this->storefrontVariants(product: $this->guitarProduct);

        $this->assertCount(1, $variants);
        $this->assertSame(expected: $variantId, actual: $variants[0]['id']);
        $this->assertSame(expected: 'iced-tea-burst', actual: $variants[0]['finishSlug']);
        $this->assertSame(expected: 'Iced Tea Burst', actual: $variants[0]['finishLabel']);
        $this->assertSame(expected: '#C67A3D', actual: $variants[0]['finishHex']);
        $this->assertSame(expected: 'nuevo', actual: $variants[0]['condition']);
        $this->assertSame(expected: 2, actual: $variants[0]['stock']);
        $this->assertSame(expected: 'left-handed', actual: $variants[0]['handedness']);

        // El `stock` del producto se arma sumando las variantes visibles:
        // si la variante se perdiera en el filtro, esto valdría 0.
        $this->getJson(uri: self::STOREFRONT_BASE.'/'.$this->guitarProduct->slug)
            ->assertJsonPath(path: 'stock', expect: 2);

        // (b) Por el Resource, que es donde vive el filtro.
        $resource = $this->resourceFor(product: $this->guitarProduct);
        $this->assertCount(expectedCount: 1, haystack: $resource['variants']);
    }

    #[Test]
    public function the_admin_api_refuses_to_create_a_finishless_variant_of_a_finishable_category(): void
    {
        // Esta es la garantía: el defecto se vuelve un 422 explícito en
        // el borde de escritura, no una ausencia silenciosa en la PDP.
        $response = $this->postJson(uri: self::ADMIN_BASE, data: [
            'product_id' => $this->guitarProduct->id,
            'name' => 'Sin acabado',
            'sku' => 'LP-NOFIN-01',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['finish_slug']);

        $this->assertDatabaseMissing(table: 'product_variants', data: ['sku' => 'LP-NOFIN-01']);
        $this->assertSame(expected: [], actual: $this->storefrontVariants(product: $this->guitarProduct));
    }

    #[Test]
    public function the_admin_api_refuses_to_strip_the_finish_of_an_existing_finishable_variant(): void
    {
        $created = $this->postJson(uri: self::ADMIN_BASE, data: [
            'product_id' => $this->guitarProduct->id,
            'name' => 'Ebony',
            'sku' => 'LP-EBONY-01',
            'finish_slug' => 'ebony',
            'stock' => 1,
        ]);
        $created->assertCreated();
        $variantId = (int) $created->json(key: 'data.id');

        $response = $this->putJson(uri: self::ADMIN_BASE.'/'.$variantId, data: [
            'finish_slug' => null,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['finish_slug']);

        // Sigue visible: el intento fallido no la dejó fuera de la PDP.
        $variants = $this->storefrontVariants(product: $this->guitarProduct);
        $this->assertCount(1, $variants);
        $this->assertSame(expected: 'ebony', actual: $variants[0]['finishSlug']);
    }

    #[Test]
    public function a_non_finishable_category_accepts_a_variant_without_finish_and_the_storefront_filter_drops_it(): void
    {
        // Comportamiento DOCUMENTADO, no un accidente: en `capos` el
        // color es un atributo del producto, no un acabado. La API admin
        // acepta el alta sin `finish_slug` y `resolveVariants()` la
        // descarta porque el picker de acabado no la necesita.
        $created = $this->postJson(uri: self::ADMIN_BASE, data: [
            'product_id' => $this->capoProduct->id,
            'name' => 'Maestro Negro',
            'sku' => 'CAPO-NEGRO-01',
            'condition' => 'nuevo',
            'stock' => 5,
        ]);

        $created->assertCreated();
        $created->assertJsonPath(path: 'data.finish_slug', expect: null);

        $this->assertDatabaseHas(table: 'product_variants', data: [
            'sku' => 'CAPO-NEGRO-01',
            'finish_slug' => null,
            'stock' => 5,
        ]);

        // El producto se sirve igual: la PDP no da 404, simplemente no
        // hay variantes que ofrecer en el picker de acabado.
        $response = $this->getJson(uri: self::STOREFRONT_BASE.'/'.$this->capoProduct->slug);
        $response->assertOk();
        $this->assertSame(expected: [], actual: $response->json(key: 'variants'));

        // Y el Resource directo coincide con el endpoint.
        $resource = $this->resourceFor(product: $this->capoProduct);
        $this->assertSame(expected: [], actual: $resource['variants']);
    }

    #[Test]
    public function a_non_finishable_variant_with_a_finish_is_visible_normally(): void
    {
        // El caso feliz del mismo camino: si el admin escribe un acabado
        // para un capo, la variante aparece con su etiqueta.
        $created = $this->postJson(uri: self::ADMIN_BASE, data: [
            'product_id' => $this->capoProduct->id,
            'name' => 'Maestro Natural',
            'sku' => 'CAPO-NAT-01',
            'finish_slug' => 'natural',
            'finish_label' => 'Natural',
        ]);
        $created->assertCreated();

        $variants = $this->storefrontVariants(product: $this->capoProduct);

        $this->assertCount(1, $variants);
        $this->assertSame(expected: 'natural', actual: $variants[0]['finishSlug']);
        $this->assertSame(expected: 'Natural', actual: $variants[0]['finishLabel']);
    }

    /**
     * Resuelve el Resource con `variants` eager-loaded, igual que hace
     * el `show()` del storefront.
     *
     * @return array<string, mixed>
     */
    private function resourceFor(Product $product): array
    {
        $product->load(relations: ['category', 'brand', 'images', 'variants']);

        return (new StorefrontProductResource($product))->resolve(request: $this->app['request']);
    }
}
