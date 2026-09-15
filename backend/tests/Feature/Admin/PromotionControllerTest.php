<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Product;
use App\Models\Marketing\Promotion;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del CRUD admin de promociones y sync de productos (RF-MKT-061).
 *
 * Cubre:
 *  - Auth: 401 sin token, 403 sin `marketing.manage`.
 *  - Listado paginado con aislamiento por tenant (globales + scoped visibles).
 *  - Show con product_ids cargados.
 *  - Store normaliza name, valida discount_type/value.
 *  - Store rechaza store fuera del scope.
 *  - Update acepta cambios parciales.
 *  - Destroy soft-delete.
 *  - Sync productos (attach + detach).
 *  - Cross-tenant → 404.
 */
#[Group('admin')]
#[Group('marketing')]
final class PromotionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorized;

    private Store $storeA;

    private Store $storeB;

    private Role $adminRole;

    private Currency $currency;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $this->adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();

        $this->currency = Currency::factory()->primary()->create();

        $this->storeA = Store::factory()->for($this->currency, 'currency')->create(['slug' => 'panda-estampados']);
        StoreDomain::query()->create([
            'store_id' => $this->storeA->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->storeB = Store::factory()->for($this->currency, 'currency')->create(['slug' => 'kitsune']);
        StoreDomain::query()->create([
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

        $this->unauthorized = User::factory()->create();

        $this->product = Product::factory()->for($this->currency, 'currency')->create();
    }

    #[TestAttr]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/promotions');
        $response->assertStatus(status: 401);
    }

    #[TestAttr]
    public function index_denies_user_without_marketing_manage_permission(): void
    {
        Sanctum::actingAs(user: $this->unauthorized, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/promotions');
        $response->assertStatus(status: 403);
    }

    #[TestAttr]
    public function index_returns_global_and_tenant_scoped_promotions(): void
    {
        Promotion::factory()->create(['name' => 'Global Promo', 'store_id' => null]);
        Promotion::factory()->forStore($this->storeA)->create(['name' => 'Panda Promo']);
        Promotion::factory()->forStore($this->storeB)->create(['name' => 'Kitsune Promo']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/promotions');

        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 2);
        $names = collect(value: $response->json('data'))->pluck('name')->all();
        $this->assertContains(needle: 'Global Promo', haystack: $names);
        $this->assertContains(needle: 'Panda Promo', haystack: $names);
        $this->assertNotContains(needle: 'Kitsune Promo', haystack: $names);
    }

    #[TestAttr]
    public function show_returns_promotion_with_product_ids(): void
    {
        $promotion = Promotion::factory()->forStore($this->storeA)->create(['name' => 'Con Productos']);
        $promotion->products()->attach($this->product->id);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://panda-estampados.com/api/admin/promotions/{$promotion->id}");

        $response->assertOk();
        $response->assertJsonPath(path: 'data.name', expect: 'Con Productos');
        $response->assertJsonPath(path: 'data.product_ids.0', expect: $this->product->id);
    }

    #[TestAttr]
    public function store_creates_promotion(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/admin/promotions',
            data: [
                'name' => 'Verano 2026',
                'description' => 'Promo de verano',
                'discount_type' => 'percentage',
                'value' => 20.0,
                'is_active' => true,
                'store_id' => $this->storeA->id,
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.name', expect: 'Verano 2026');
        $response->assertJsonPath(path: 'data.discount_type', expect: 'percentage');
        $response->assertJsonPath(path: 'data.is_active', expect: true);
    }

    #[TestAttr]
    public function store_rejects_store_outside_tenant_scope(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/admin/promotions',
            data: [
                'name' => 'Kitsune Promo',
                'discount_type' => 'percentage',
                'value' => 10.0,
                'store_id' => $this->storeB->id,
            ],
        );

        $response->assertStatus(status: 403);
    }

    #[TestAttr]
    public function store_validates_discount_type(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/admin/promotions',
            data: [
                'name' => 'Tipo Invalido',
                'discount_type' => 'bogus',
                'value' => 10.0,
                'store_id' => $this->storeA->id,
            ],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['discount_type']);
    }

    #[TestAttr]
    public function update_partial_fields(): void
    {
        $promotion = Promotion::factory()->forStore($this->storeA)->create(['name' => 'Original']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(
            uri: "http://panda-estampados.com/api/admin/promotions/{$promotion->id}",
            data: [
                'name' => 'Renombrado',
                'value' => 30,
                'is_active' => false,
            ],
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.name', expect: 'Renombrado');
        $response->assertJsonPath(path: 'data.value', expect: 30);
        $response->assertJsonPath(path: 'data.is_active', expect: false);
    }

    #[TestAttr]
    public function destroy_soft_deletes_promotion(): void
    {
        $promotion = Promotion::factory()->forStore($this->storeA)->create();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $this->deleteJson(uri: "http://panda-estampados.com/api/admin/promotions/{$promotion->id}")
            ->assertNoContent();

        $this->assertSoftDeleted(table: 'promotions', data: ['id' => $promotion->id]);
    }

    #[TestAttr]
    public function promotion_from_other_tenant_returns_404(): void
    {
        $otherPromotion = Promotion::factory()->forStore($this->storeB)->create();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://panda-estampados.com/api/admin/promotions/{$otherPromotion->id}");

        $response->assertStatus(status: 404);
    }

    #[TestAttr]
    public function sync_products_attaches(): void
    {
        $promotion = Promotion::factory()->forStore($this->storeA)->create();
        $product2 = Product::factory()->for($this->currency, 'currency')->create();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/admin/promotions/{$promotion->id}/products",
            data: [
                'action' => 'attach',
                'product_ids' => [$this->product->id, $product2->id],
            ],
        );

        $response->assertOk();
        $response->assertJsonCount(2, 'data.product_ids');

        // Idempotente: segundo attach de los mismos no duplica.
        $this->postJson(
            uri: "http://panda-estampados.com/api/admin/promotions/{$promotion->id}/products",
            data: [
                'action' => 'attach',
                'product_ids' => [$this->product->id],
            ],
        )->assertOk();

        $verify = $this->getJson(uri: "http://panda-estampados.com/api/admin/promotions/{$promotion->id}");
        $verify->assertJsonCount(2, 'data.product_ids');
    }

    #[TestAttr]
    public function sync_products_detaches(): void
    {
        $promotion = Promotion::factory()->forStore($this->storeA)->create();
        $promotion->products()->attach([$this->product->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/admin/promotions/{$promotion->id}/products",
            data: [
                'action' => 'detach',
                'product_ids' => [$this->product->id],
            ],
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.product_ids', expect: []);
    }

    #[TestAttr]
    public function sync_validates_product_ids_exist(): void
    {
        $promotion = Promotion::factory()->forStore($this->storeA)->create();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/admin/promotions/{$promotion->id}/products",
            data: [
                'action' => 'attach',
                'product_ids' => [99999],
            ],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['product_ids.0']);
    }
}
