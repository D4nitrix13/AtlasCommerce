<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin\Support;

use App\Models\Auth\Role;
use App\Models\Auth\User as UserModel;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\Support\ProductReview;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del controller admin `Api\Admin\Support\ReviewAdminController`.
 */
final class ReviewAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $user;

    private Store $store;

    private string $host = 'admin-reviews.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);

        $role = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $currency = Currency::factory()->primary()->create();

        $this->store = Store::factory()->for($currency, 'currency')->create();

        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => $this->host,
            'type' => 'domain',
            'base_path' => '/',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $this->user = UserModel::query()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'reviews@panda-estampados.test',
            'email_verified_at' => now(),
            'password' => 'secret',
            'is_active' => true,
            'registered_at' => now(),
            'role_id' => $role->id,
        ]);
        $this->user->stores()->attach($this->store->id);
    }

    #[TestAttr]
    public function index_filters_by_pending_flag(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();

        ProductReview::factory()->create([
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'is_approved' => false,
        ]);
        ProductReview::factory()->create([
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'product_id' => Product::factory(),
            'is_approved' => true,
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/support/reviews?pending=1");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath(path: 'data.0.is_approved', expect: false);
    }

    #[TestAttr]
    public function approve_flips_is_approved_to_true(): void
    {
        $review = ProductReview::factory()->pending()->create([
            'store_id' => $this->store->id,
            'customer_id' => Customer::factory(),
            'product_id' => Product::factory(),
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/admin/support/reviews/{$review->id}/approve");

        $response->assertOk();
        $response->assertJsonPath(path: 'data.is_approved', expect: true);
        $this->assertSame(expected: true, actual: (bool) $review->fresh()->is_approved);
    }

    #[TestAttr]
    public function reject_flips_is_approved_to_false(): void
    {
        $review = ProductReview::factory()->approved()->create([
            'store_id' => $this->store->id,
            'customer_id' => Customer::factory(),
            'product_id' => Product::factory(),
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/admin/support/reviews/{$review->id}/reject");

        $response->assertOk();
        $response->assertJsonPath(path: 'data.is_approved', expect: false);
    }

    #[TestAttr]
    public function review_from_other_tenant_returns_404(): void
    {
        $otherStore = Store::factory()->for($this->store->currency, 'currency')->create();
        $review = ProductReview::factory()->create([
            'store_id' => $otherStore->id,
            'customer_id' => Customer::factory(),
            'product_id' => Product::factory(),
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/support/reviews/{$review->id}");

        $response->assertStatus(status: 404);
    }
}
