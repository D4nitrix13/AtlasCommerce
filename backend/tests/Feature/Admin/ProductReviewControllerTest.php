<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\Support\ProductReview;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del CRUD de moderación admin de reseñas (RF-SUP-161).
 *
 * Cubre:
 *  - Auth: 401 sin token, 403 sin `support.manage`.
 *  - Listado con filtros (product_id, rating, is_approved) + tenant isolation.
 *  - Approve cambia is_approved a true.
 *  - Reject cambia is_approved a false.
 *  - Destroy soft-delete.
 */
#[Group('admin')]
#[Group('reviews')]
final class ProductReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorized;

    private Product $product;

    private Store $store;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();

        $currency = Currency::factory()->primary()->create();

        $this->store = Store::factory()->for($currency, 'currency')->create(['slug' => 'panda-estampados']);
        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'email' => 'admin@panda-estampados.com',
            'is_active' => true,
            'role_id' => $adminRole->id,
        ]);
        $this->admin->stores()->syncWithoutDetaching(ids: [$this->store->id]);

        $this->unauthorized = User::factory()->create();

        $this->customer = Customer::factory()->create();
        $this->product = Product::factory()->for($currency, 'currency')->create();
    }

    #[TestAttr]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/reviews');
        $response->assertStatus(status: 401);
    }

    #[TestAttr]
    public function index_denies_user_without_support_view(): void
    {
        Sanctum::actingAs(user: $this->unauthorized, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/reviews');
        $response->assertStatus(status: 403);
    }

    #[TestAttr]
    public function index_filters_by_product_and_approval(): void
    {
        ProductReview::factory()->create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'rating' => 5,
            'is_approved' => true,
        ]);
        ProductReview::factory()->create([
            'product_id' => $this->product->id,
            'customer_id' => Customer::factory()->create(),
            'store_id' => $this->store->id,
            'is_approved' => false,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $onlyApproved = $this->getJson(uri: 'http://panda-estampados.com/api/admin/reviews?is_approved=true');
        $onlyApproved->assertJsonPath(path: 'meta.total', expect: 1);

        $byProduct = $this->getJson(uri: "http://panda-estampados.com/api/admin/reviews?product_id={$this->product->id}");
        $byProduct->assertJsonPath(path: 'meta.total', expect: 2);
    }

    #[TestAttr]
    public function approve_changes_is_approved_to_true(): void
    {
        $review = ProductReview::factory()->pending()->create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: "http://panda-estampados.com/api/admin/reviews/{$review->id}/approve");

        $response->assertOk();
        $response->assertJsonPath(path: 'data.is_approved', expect: true);
    }

    #[TestAttr]
    public function reject_changes_is_approved_to_false(): void
    {
        $review = ProductReview::factory()->approved()->create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: "http://panda-estampados.com/api/admin/reviews/{$review->id}/reject");

        $response->assertOk();
        $response->assertJsonPath(path: 'data.is_approved', expect: false);
    }

    #[TestAttr]
    public function destroy_soft_deletes_review(): void
    {
        $review = ProductReview::factory()->create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $this->deleteJson(uri: "http://panda-estampados.com/api/admin/reviews/{$review->id}")
            ->assertNoContent();

        $this->assertSoftDeleted(table: 'product_reviews', data: ['id' => $review->id]);
    }

    #[TestAttr]
    public function review_from_other_tenant_returns_404(): void
    {
        $otherStore = Store::factory()->for($this->store->currency, 'currency')->create();
        $review = ProductReview::factory()->create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'store_id' => $otherStore->id,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://panda-estampados.com/api/admin/reviews/{$review->id}");

        $response->assertStatus(status: 404);
    }
}
