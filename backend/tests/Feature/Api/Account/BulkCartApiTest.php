<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Account;

use App\Models\Account\BulkCartItem;
use App\Models\Auth\User as UserModel;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del controller `Account/bulk-cart` (F3).
 */
final class BulkCartApiTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $user;

    private Customer $customer;

    private Store $store;

    private string $host = 'account-bulk.test';

    protected function setUp(): void
    {
        parent::setUp();

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
            'email' => 'bulk-cart@test.test',
            'email_verified_at' => now(),
            'password' => 'secret',
            'is_active' => true,
            'registered_at' => now(),
            'role_id' => null,
        ]);

        $this->customer = Customer::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $this->customer->stores()->attach($this->store->id);
    }

    #[TestAttr]
    public function it_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/account/bulk-cart");

        $response->assertStatus(status: 401);
    }

    #[TestAttr]
    public function index_returns_only_own_items(): void
    {
        $otherCustomer = Customer::factory()->create();
        BulkCartItem::factory()->create([
            'customer_id' => $this->customer->id,
            'product_id' => Product::factory(),
        ]);
        BulkCartItem::factory()->create([
            'customer_id' => $otherCustomer->id,
            'product_id' => Product::factory(),
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/account/bulk-cart");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[TestAttr]
    public function store_creates_item_linked_to_authenticated_customer(): void
    {
        $product = Product::factory()->create();

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://{$this->host}/api/v1/account/bulk-cart",
            data: [
                'product_id' => $product->id,
                'quantity' => 200,
                'notes' => 'Para distribución en Managua',
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.quantity', expect: 200);
        $this->assertSame(expected: $this->customer->id, actual: $response->json('data.customer_id'));
    }

    #[TestAttr]
    public function update_modifies_quantity(): void
    {
        $item = BulkCartItem::factory()->create([
            'customer_id' => $this->customer->id,
            'product_id' => Product::factory(),
            'quantity' => 50,
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->putJson(
            uri: "http://{$this->host}/api/v1/account/bulk-cart/{$item->id}",
            data: ['quantity' => 150],
        );

        $response->assertOk();
        $this->assertSame(expected: 150, actual: (int) $item->fresh()->quantity);
    }

    #[TestAttr]
    public function cannot_update_another_users_item(): void
    {
        $otherCustomer = Customer::factory()->create();
        $item = BulkCartItem::factory()->create([
            'customer_id' => $otherCustomer->id,
            'product_id' => Product::factory(),
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->putJson(
            uri: "http://{$this->host}/api/v1/account/bulk-cart/{$item->id}",
            data: ['quantity' => 999],
        );

        $response->assertStatus(status: 403);
    }

    #[TestAttr]
    public function destroy_removes_item(): void
    {
        $item = BulkCartItem::factory()->create([
            'customer_id' => $this->customer->id,
            'product_id' => Product::factory(),
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->deleteJson(uri: "http://{$this->host}/api/v1/account/bulk-cart/{$item->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing(table: 'bulk_cart_items', data: ['id' => $item->id]);
    }

    #[TestAttr]
    public function validation_requires_positive_quantity(): void
    {
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://{$this->host}/api/v1/account/bulk-cart",
            data: [
                'product_id' => Product::factory()->create()->id,
                'quantity' => 0,
            ],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(['quantity']);
    }
}
