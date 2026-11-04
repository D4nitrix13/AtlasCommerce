<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Services\Catalog\ProductPhotoGrouper;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del CRUD admin de imágenes de producto (RF-CAT-022).
 *
 * Cubre:
 *  - Auth (401, 403 sin catalog.manage).
 *  - Listado + filtros (is_primary) + aislamiento por tenant.
 *  - Show + 404 fuera de scope o cross-product.
 *  - Store con validación (image_url requerida).
 *  - Store primary=true desmarca otras primarias.
 *  - sort_order auto-asignado si no se pasa.
 *  - Update + cross-product validation.
 *  - variant_id: se persiste, se puede re-apuntar y quitar, y una variante
 *    de otro producto es 422 en store y en update.
 *  - is_hover: exclusividad por producto (clearHoverFlag), conflicto 422
 *    con la ranura de portada, y respuesta del Resource.
 *  - Reorder de galería: reescribe sort_order por foto (no por fila),
 *    exige el juego completo de ids y rechaza ajenos/duplicados.
 *  - Destroy.
 */
#[Group('admin')]
#[Group('catalog')]
final class ProductImageControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private Role $adminRole;

    private Role $staffRole;

    private Store $storeA;

    private Store $storeB;

    private Product $product;

    private Product $otherProduct;

    private Category $category;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $this->adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $this->staffRole = Role::query()->where(column: 'slug', operator: '=', value: 'staff')->firstOrFail();

        $currency = Currency::factory()->primary()->create();
        $this->category = Category::factory()->create();
        $this->brand = Brand::factory()->create();

        $storeASlug = 'panda-'.Str::uuid()->toString();
        $storeBSlug = 'kitsune-'.Str::uuid()->toString();

        $this->storeA = Store::factory()->for($currency, 'currency')->create([
            'slug' => $storeASlug,
        ]);
        StoreDomain::query()->create(attributes: [
            'store_id' => $this->storeA->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->storeB = Store::factory()->for($currency, 'currency')->create([
            'slug' => $storeBSlug,
        ]);
        StoreDomain::query()->create(attributes: [
            'store_id' => $this->storeB->id,
            'domain' => 'kitsune.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'email' => 'admin@panda-estampados.com',
            'is_active' => true,
            'role_id' => $this->adminRole->id,
        ]);
        $this->admin->stores()->syncWithoutDetaching(ids: [$this->storeA->id]);

        $this->staff = User::factory()->create([
            'email' => 'staff@panda-estampados.com',
            'is_active' => true,
            'role_id' => $this->staffRole->id,
        ]);
        $this->staff->stores()->syncWithoutDetaching(ids: [$this->storeA->id]);

        $this->product = $this->makeProduct(store: $this->storeA);
        $this->otherProduct = $this->makeProduct(store: $this->storeB);
    }

    private function makeProduct(Store $store, array $attrs = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => Currency::factory()->primary()->create()->id,
            'is_active' => true,
            'published_at' => now(),
        ], $attrs));

        ProductStore::query()->create(attributes: [
            'product_id' => $product->id,
            'store_id' => $store->id,
            'is_visible' => true,
        ]);

        return $product;
    }

    private function makeImage(Product $product, array $attrs = []): ProductImage
    {
        return ProductImage::query()->create(array_merge([
            'product_id' => $product->id,
            'image_url' => 'https://cdn.example.com/img.jpg',
            'alt_text' => 'Test',
            'is_primary' => false,
            'sort_order' => 0,
        ], $attrs));
    }

    private function makeVariant(Product $product, string $finish): ProductVariant
    {
        return ProductVariant::factory()->for($product, 'product')->create(attributes: [
            'finish_slug' => $finish,
            'finish_label' => ucfirst($finish),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images');
        $response->assertStatus(status: 401);
    }

    #[Test]
    public function index_denies_user_without_catalog_manage_permission(): void
    {
        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images');
        $response->assertStatus(status: 403);
    }

    #[Test]
    public function index_returns_images_for_product_in_tenant(): void
    {
        $this->makeImage($this->product, ['image_url' => 'https://a.jpg', 'sort_order' => 1]);
        $this->makeImage($this->product, ['image_url' => 'https://b.jpg', 'sort_order' => 2]);
        $this->makeImage($this->otherProduct, ['image_url' => 'https://c.jpg']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function index_returns_404_for_product_outside_tenant(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->otherProduct->id.'/images');

        $response->assertStatus(status: 404);
    }

    #[Test]
    public function index_filters_by_is_primary(): void
    {
        $this->makeImage($this->product, ['image_url' => 'https://a.jpg', 'is_primary' => true]);
        $this->makeImage($this->product, ['image_url' => 'https://b.jpg', 'is_primary' => false]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images?is_primary=1');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function show_returns_404_for_image_of_other_product(): void
    {
        $image = $this->makeImage($this->otherProduct);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/'.$image->id);

        $response->assertStatus(status: 404);
    }

    #[Test]
    public function store_creates_image_with_auto_sort_order(): void
    {
        $this->makeImage($this->product, ['sort_order' => 5]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images', data: [
            'image_url' => 'https://cdn.example.com/new.jpg',
            'alt_text' => 'New',
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.sort_order', expect: 6); // max(5) + 1
        $response->assertJsonPath(path: 'data.is_primary', expect: false);
    }

    #[Test]
    public function store_with_explicit_sort_order(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images', data: [
            'image_url' => 'https://cdn.example.com/x.jpg',
            'sort_order' => 10,
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.sort_order', expect: 10);
    }

    #[Test]
    public function store_rejects_empty_image_url(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images', data: [
            'image_url' => '',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['image_url']);
    }

    #[Test]
    public function store_with_primary_unmarks_existing_primary(): void
    {
        $existing = $this->makeImage($this->product, ['is_primary' => true]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images', data: [
            'image_url' => 'https://cdn.example.com/new-primary.jpg',
            'is_primary' => true,
        ]);

        $response->assertCreated();

        // La imagen vieja ya NO es primaria.
        $existing->refresh();
        $this->assertFalse($existing->is_primary);

        // La nueva sí es primaria.
        $newId = $response->json(key: 'data.id');
        $new = ProductImage::query()->find($newId);
        $this->assertTrue($new->is_primary);
    }

    #[Test]
    public function store_blocks_for_product_outside_tenant(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->otherProduct->id.'/images', data: [
            'image_url' => 'https://cdn.example.com/x.jpg',
        ]);

        $response->assertStatus(status: 404);
    }

    #[Test]
    public function update_modifies_image_fields(): void
    {
        $image = $this->makeImage($this->product, ['alt_text' => 'Original']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/'.$image->id, data: [
            'alt_text' => 'Renombrado',
            'sort_order' => 99,
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.alt_text', expect: 'Renombrado');
        $response->assertJsonPath(path: 'data.sort_order', expect: 99);
    }

    #[Test]
    public function update_setting_primary_unmarks_existing(): void
    {
        $old = $this->makeImage($this->product, ['is_primary' => true]);
        $new = $this->makeImage($this->product, ['is_primary' => false]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/'.$new->id, data: [
            'is_primary' => true,
        ]);

        $response->assertOk();

        $old->refresh();
        $new->refresh();
        $this->assertFalse($old->is_primary);
        $this->assertTrue($new->is_primary);
    }

    // -----------------------------------------------------------------------
    // variant_id — propiedad y corrección de la association
    // -----------------------------------------------------------------------

    #[Test]
    public function store_persists_a_variant_owned_by_the_product(): void
    {
        $variant = $this->makeVariant(product: $this->product, finish: 'heritage-cherry');

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images', data: [
            'image_url' => 'https://cdn.example.com/cherry.jpg',
            'variant_id' => $variant->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.variant_id', expect: $variant->id);

        $row = ProductImage::query()->firstOrFail();
        $this->assertSame(expected: $variant->id, actual: (int) $row->variant_id);
    }

    #[Test]
    public function store_without_variant_id_stores_null(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images', data: [
            'image_url' => 'https://cdn.example.com/x.jpg',
        ]);

        $response->assertCreated();
        $this->assertNull($response->json(key: 'data.variant_id'));
        $this->assertNull(ProductImage::query()->firstOrFail()->variant_id);
    }

    #[Test]
    public function store_rejects_a_variant_of_another_product(): void
    {
        $foreign = $this->makeVariant(product: $this->otherProduct, finish: 'sunburst');

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images', data: [
            'image_url' => 'https://cdn.example.com/x.jpg',
            'variant_id' => $foreign->id,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['variant_id']);
        $this->assertSame(expected: 0, actual: ProductImage::query()->count());
    }

    #[Test]
    public function update_repoints_the_image_to_a_variant_of_the_same_product(): void
    {
        $cherry = $this->makeVariant(product: $this->product, finish: 'heritage-cherry');
        $sunburst = $this->makeVariant(product: $this->product, finish: 'sunburst');
        $image = $this->makeImage($this->product, ['variant_id' => $cherry->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/'.$image->id, data: [
            'variant_id' => $sunburst->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.variant_id', expect: $sunburst->id);

        $image->refresh();
        $this->assertSame(expected: $sunburst->id, actual: (int) $image->variant_id);
    }

    #[Test]
    public function update_can_detach_the_variant(): void
    {
        $variant = $this->makeVariant(product: $this->product, finish: 'heritage-cherry');
        $image = $this->makeImage($this->product, ['variant_id' => $variant->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/'.$image->id, data: [
            'variant_id' => null,
        ]);

        $response->assertOk();
        $this->assertNull($response->json(key: 'data.variant_id'));

        $image->refresh();
        $this->assertNull($image->variant_id);
    }

    #[Test]
    public function update_rejects_repointing_the_image_to_a_foreign_variant(): void
    {
        $cherry = $this->makeVariant(product: $this->product, finish: 'heritage-cherry');
        $foreign = $this->makeVariant(product: $this->otherProduct, finish: 'sunburst');
        $image = $this->makeImage($this->product, ['variant_id' => $cherry->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/'.$image->id, data: [
            'variant_id' => $foreign->id,
        ]);

        // 422 y la fila intacta: sin esto, una foto del producto A queda
        // etiquetada con un acabado del producto B y el filtro de galería
        // del PDP miente.
        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['variant_id']);

        $image->refresh();
        $this->assertSame(expected: $cherry->id, actual: (int) $image->variant_id);
    }

    #[Test]
    public function the_resource_exposes_variant_id_even_when_it_is_null(): void
    {
        $image = $this->makeImage($this->product);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/'.$image->id);

        $response->assertOk();
        // El campo está siempre: el panel necesita distinguir "sin
        // variante" de "el endpoint no lo manda".
        $this->assertTrue($response->json(key: 'data') !== null);
        $this->assertArrayHasKey(key: 'variant_id', array: $response->json(key: 'data'));
        $this->assertNull($response->json(key: 'data.variant_id'));
    }

    #[Test]
    public function destroy_deletes_image(): void
    {
        $image = $this->makeImage($this->product);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/'.$image->id);

        $response->assertNoContent();
        $this->assertNull(ProductImage::query()->find($image->id));
    }

    #[Test]
    public function destroy_blocks_cross_product(): void
    {
        $image = $this->makeImage($this->otherProduct);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/'.$image->id);

        $response->assertStatus(status: 404);
    }

    // -----------------------------------------------------------------------
    // is_hover — ranura de hover: exclusividad, limpieza y conflicto
    // -----------------------------------------------------------------------

    /**
     * Una foto = N filas que comparten `(variant_id, sort_order)` y se
     * distinguen por `width` ({@see ProductPhotoGrouper}).
     *
     * @param  list<int>  $widths
     * @return list<ProductImage>
     */
    private function makePhoto(Product $product, int $sortOrder, array $widths = [400, 800, 1200, 1600]): array
    {
        $rows = [];

        foreach ($widths as $width) {
            $rows[] = $this->makeImage($product, [
                'sort_order' => $sortOrder,
                'width' => $width,
                'image_url' => 'https://cdn.example.com/photo-'.$sortOrder.'-'.$width.'.webp',
            ]);
        }

        return $rows;
    }

    #[Test]
    public function it_unmarks_the_previous_hover_rows_when_store_creates_a_hover_image(): void
    {
        // La marca vive en TODAS las filas de la foto: limpiar sólo una
        // dejaría al resto mintiendo (ver nota de granularidad en la
        // migración de is_hover).
        $previousWide = $this->makeImage($this->product, ['is_hover' => true, 'sort_order' => 0, 'width' => 1600]);
        $previousNarrow = $this->makeImage($this->product, ['is_hover' => true, 'sort_order' => 0, 'width' => 800]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images', data: [
            'image_url' => 'https://cdn.example.com/hover.jpg',
            'is_hover' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.is_hover', expect: true);

        $previousWide->refresh();
        $previousNarrow->refresh();
        $this->assertFalse($previousWide->is_hover);
        $this->assertFalse($previousNarrow->is_hover);
    }

    #[Test]
    public function it_rejects_a_store_that_marks_the_same_photo_as_primary_and_hover(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images', data: [
            'image_url' => 'https://cdn.example.com/x.jpg',
            'is_primary' => true,
            'is_hover' => true,
        ]);

        // 422 y nada escrito: resolver el conflicto en silencio
        // cambiaría una ranura que el admin no pidió cambiar.
        $response->assertStatus(status: 422);
        $this->assertSame(expected: 0, actual: ProductImage::query()->count());
    }

    #[Test]
    public function it_unmarks_the_previous_hover_rows_when_update_promotes_another_photo_to_hover(): void
    {
        $previous = $this->makeImage($this->product, ['is_hover' => true, 'sort_order' => 0]);
        $target = $this->makeImage($this->product, ['sort_order' => 1]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/'.$target->id, data: [
            'is_hover' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.is_hover', expect: true);

        $previous->refresh();
        $this->assertFalse($previous->is_hover);
    }

    #[Test]
    public function it_rejects_promoting_the_primary_photo_to_hover(): void
    {
        $image = $this->makeImage($this->product, ['is_primary' => true]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/'.$image->id, data: [
            'is_hover' => true,
        ]);

        // La portada sigue siendo portada: el update no muta nada.
        $response->assertStatus(status: 422);

        $image->refresh();
        $this->assertTrue($image->is_primary);
        $this->assertFalse($image->is_hover);
    }

    #[Test]
    public function it_rejects_promoting_the_hover_photo_to_primary(): void
    {
        $image = $this->makeImage($this->product, ['is_hover' => true]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/'.$image->id, data: [
            'is_primary' => true,
        ]);

        // Sin bajar is_hover en el mismo body la foto quedaría en las dos
        // ranuras, y eso se rechaza en vez de resolverlo solo.
        $response->assertStatus(status: 422);

        $image->refresh();
        $this->assertFalse($image->is_primary);
        $this->assertTrue($image->is_hover);
    }

    #[Test]
    public function it_moves_a_photo_from_hover_to_primary_in_a_single_update(): void
    {
        $image = $this->makeImage($this->product, ['is_hover' => true]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/'.$image->id, data: [
            'is_primary' => true,
            'is_hover' => false,
        ]);

        $response->assertOk();

        $image->refresh();
        $this->assertTrue($image->is_primary);
        $this->assertFalse($image->is_hover);
    }

    #[Test]
    public function it_keeps_the_hover_flag_when_the_update_does_not_touch_it(): void
    {
        $image = $this->makeImage($this->product, ['is_hover' => true]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/'.$image->id, data: [
            'alt_text' => 'Renombrado',
        ]);

        $response->assertOk();

        $image->refresh();
        $this->assertTrue($image->is_hover);
    }

    // -----------------------------------------------------------------------
    // reorder — orden de galería a nivel de foto, no de fila
    // -----------------------------------------------------------------------

    #[Test]
    public function it_rewrites_sort_order_following_the_payload_order(): void
    {
        $first = $this->makeImage($this->product, ['sort_order' => 0, 'image_url' => 'https://a.jpg']);
        $second = $this->makeImage($this->product, ['sort_order' => 1, 'image_url' => 'https://b.jpg']);
        $third = $this->makeImage($this->product, ['sort_order' => 2, 'image_url' => 'https://c.jpg']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/order', data: [
            'image_ids' => [$third->id, $first->id, $second->id],
        ]);

        $response->assertOk();

        // Cada fila quedó exactamente en la posición pedida, renumerada
        // desde 0: sort_order es el orden de galería, no un índice libre.
        $this->assertSame(expected: 0, actual: (int) $third->fresh()->sort_order);
        $this->assertSame(expected: 1, actual: (int) $first->fresh()->sort_order);
        $this->assertSame(expected: 2, actual: (int) $second->fresh()->sort_order);
    }

    #[Test]
    public function it_keeps_every_resolution_of_a_photo_in_the_same_position(): void
    {
        $photoA = $this->makePhoto(product: $this->product, sortOrder: 0);
        $photoB = $this->makePhoto(product: $this->product, sortOrder: 1);

        // Una fila de B primero y las de A intercaladas: los ids no
        // tienen que venir contiguos por foto, manda la primera
        // aparición de cada una.
        $ids = [
            $photoB[0]->id, $photoA[0]->id, $photoA[1]->id, $photoB[1]->id,
            $photoA[2]->id, $photoB[2]->id, $photoA[3]->id, $photoB[3]->id,
        ];

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/order', data: [
            'image_ids' => $ids,
        ]);

        $response->assertOk();

        // B completo en la posición 0, A completo en la 1: las 4
        // resoluciones de cada foto comparten el sort_order nuevo, o el
        // srcset de la galería se rompe.
        foreach ($photoB as $row) {
            $this->assertSame(expected: 0, actual: (int) $row->fresh()->sort_order);
        }

        foreach ($photoA as $row) {
            $this->assertSame(expected: 1, actual: (int) $row->fresh()->sort_order);
        }
    }

    #[Test]
    public function it_rejects_an_order_payload_that_misses_an_image(): void
    {
        $kept = $this->makeImage($this->product, ['sort_order' => 0, 'image_url' => 'https://a.jpg']);
        $missing = $this->makeImage($this->product, ['sort_order' => 1, 'image_url' => 'https://b.jpg']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/order', data: [
            'image_ids' => [$kept->id],
        ]);

        // 422 y ninguna escritura: un reorder parcial dejaría la galería
        // en un estado que nadie pidió.
        $response->assertStatus(status: 422);

        $missing->refresh();
        $this->assertSame(expected: 1, actual: (int) $missing->sort_order);
    }

    #[Test]
    public function it_rejects_an_order_payload_with_an_image_of_another_product(): void
    {
        $own = $this->makeImage($this->product);
        $foreign = $this->makeImage($this->otherProduct);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/order', data: [
            'image_ids' => [$own->id, $foreign->id],
        ]);

        $response->assertStatus(status: 422);
    }

    #[Test]
    public function it_rejects_an_order_payload_with_duplicate_ids(): void
    {
        $image = $this->makeImage($this->product);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$this->product->id.'/images/order', data: [
            'image_ids' => [$image->id, $image->id],
        ]);

        // Un id repetido pondría dos fotos en la misma posición.
        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['image_ids.1']);
    }
}
