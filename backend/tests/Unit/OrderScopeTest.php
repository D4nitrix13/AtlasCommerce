<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Customers\Customer;
use App\Models\Sales\Order;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests unitarios de los scopes definidos en `Order`.
 *
 * El scope `scopeByCustomer` filtra por `customer_id`. Pre-PR 1 contenía
 * un typo (`custstomer_id`) que causaba SQLSTATE column-not-found.
 */
final class OrderScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_only_orders_for_the_requested_customer(): void
    {
        // Arrange: 3 customers con 1 order cada uno.
        $store = Store::factory()->for(Currency::factory(), 'currency')->create();
        $customerA = Customer::factory()->create();
        $customerB = Customer::factory()->create();
        $customerC = Customer::factory()->create();

        Order::factory()->for($customerA)->for($store)->for(Currency::factory(), 'currency')->create();
        Order::factory()->for($customerB)->for($store)->for(Currency::factory(), 'currency')->create();
        Order::factory()->for($customerC)->for($store)->for(Currency::factory(), 'currency')->create();

        // Act: filtrar por customerB.
        $result = Order::query()->byCustomer($customerB->id)->get();

        // Assert: solo 1 order, la del customerB.
        $this->assertSame(1, $result->count());
        $this->assertSame($customerB->id, $result->first()->customer_id);
    }
}
