<?php

namespace Database\Factories\Payments;

use App\Models\Customers\Customer;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentMethod;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'store_id' => Store::factory(),
            'currency_id' => Currency::factory(),
            'amount_cents' => 10_000,
            'exchange_rate_snapshot' => 1.0,
            'reference' => fake()->bothify('REF-########'),
            'status' => 'completed',
            'paid_at' => now(),
        ];
    }
}
