<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Sales\Order;
use App\Models\Settings\Currency;
use App\Models\Stores\SalesChannel;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $store = Store::factory();
        $currency = Currency::factory();
        $customer = Customer::factory();
        $address = CustomerAddress::factory()
            ->for($customer)
            ->create();
        $channel = SalesChannel::factory()->for($store)->create();

        return [
            'customer_id' => $customer,
            'shipping_address_id' => $address->id,
            'store_id' => $store,
            'sales_channel_id' => $channel->id,
            'user_creator_id' => null,
            'currency_id' => $currency,
            'order_number' => strtoupper(fake()->unique()->bothify('ORD-########')),
            'subtotal_cents' => 10_000,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 0,
            'total_cents' => 10_000,
            'exchange_rate_snapshot' => 1.0,
            'total_in_base_currency_cents' => 10_000,
            'status' => 'pending',
            'ordered_at' => now(),
        ];
    }
}
