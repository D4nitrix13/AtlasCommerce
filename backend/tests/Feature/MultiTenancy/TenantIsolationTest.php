<?php

declare(strict_types=1);

namespace Tests\Feature\MultiTenancy;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Sales\Order;
use App\Models\Settings\Currency;
use App\Models\Stores\SalesChannel;
use App\Models\Stores\Store;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('multitenancy')]
final class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function order_query_is_scoped_to_current_store(): void
    {
        $storeA = $this->makeStore('store-a');
        $storeB = $this->makeStore('store-b');

        $orderA = $this->makeOrder($storeA);
        $orderB = $this->makeOrder($storeB);

        $this->assertSame(2, Order::withoutTenantScope()->count());

        app(StoreContextService::class)->setStore($storeA);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame($orderA->id, Order::query()->first()->id);

        app(StoreContextService::class)->setStore($storeB);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame($orderB->id, Order::query()->first()->id);
    }

    #[Test]
    public function for_store_scope_overrides_context(): void
    {
        $storeA = $this->makeStore('store-a');
        $storeB = $this->makeStore('store-b');

        $this->makeOrder($storeA);
        $this->makeOrder($storeB);

        app(StoreContextService::class)->setStore($storeA);

        $countB = Order::query()->forStore($storeB->id)->count();

        $this->assertSame(1, $countB);
    }

    #[Test]
    public function without_store_scope_bypasses_the_global_filter(): void
    {
        $storeA = $this->makeStore('store-a');
        $storeB = $this->makeStore('store-b');

        $this->makeOrder($storeA);
        $this->makeOrder($storeB);

        app(StoreContextService::class)->setStore($storeA);

        $all = Order::query()->withoutStoreScope()->count();

        $this->assertSame(2, $all);
    }

    #[Test]
    public function customer_api_token_creation_works(): void
    {
        $customer = Customer::create([
            'first_name' => 'Guest',
            'last_name' => 'Buyer',
            'email' => 'guest@test.test',
        ]);

        $token = $customer->createToken('checkout');

        $this->assertNotNull($token->plainTextToken);
        $this->assertCount(1, $customer->fresh()->tokens);
    }

    private function sharedCurrency(): Currency
    {
        return Currency::firstOrCreate(
            ['code' => 'NIO'],
            [
                'name' => 'Córdoba',
                'symbol' => 'C$',
                'decimal_places' => 2,
                'is_primary' => true,
                'is_active' => true,
            ],
        );
    }

    private function makeStore(string $slug): Store
    {
        return Store::create([
            'currency_id' => $this->sharedCurrency()->id,
            'name' => $slug,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function makeOrder(Store $store): Order
    {
        $channel = SalesChannel::create([
            'store_id' => $store->id,
            'code' => 'web',
            'name' => 'Web',
            'type' => 'online',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'General',
            'slug' => 'general-'.$store->slug,
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'currency_id' => $store->currency_id,
            'name' => 'P',
            'slug' => 'p-'.$store->slug,
            'sku' => 'SKU-'.$store->slug,
            'base_price_cents' => 1000,
            'cost_cents' => 0,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'first_name' => 'B',
            'last_name' => 'X',
            'email' => $store->slug.'@test.test',
        ]);

        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'address_type' => 'shipping',
            'country' => 'Nicaragua',
            'detailed_address' => 'X',
            'is_primary' => true,
        ]);

        return Order::create([
            'customer_id' => $customer->id,
            'shipping_address_id' => $address->id,
            'store_id' => $store->id,
            'sales_channel_id' => $channel->id,
            'currency_id' => $store->currency_id,
            'order_number' => 'ORD-'.uniqid($store->slug),
            'subtotal_cents' => 1000,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 0,
            'total_cents' => 1000,
            'exchange_rate_snapshot' => 1.0,
            'total_in_base_currency_cents' => 1000,
            'status' => 'pending',
            'ordered_at' => now(),
        ]);
    }
}
