<?php

declare(strict_types=1);

namespace Database\Factories\Carts;

use App\Models\Carts\Cart;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'store_id' => Store::factory(),
            'currency_id' => Currency::factory(),
            'status' => 'active',
            'expires_at' => now()->addDays(7),
        ];
    }
}
