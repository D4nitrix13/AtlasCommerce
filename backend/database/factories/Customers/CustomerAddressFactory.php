<?php

declare(strict_types=1);

namespace Database\Factories\Customers;

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'address_type' => 'shipping',
            'country' => 'Nicaragua',
            'department' => fake()->randomElement(['Managua', 'León', 'Granada', 'Masaya']),
            'municipality' => fake()->city(),
            'detailed_address' => fake()->streetAddress(),
            'reference' => fake()->sentence(),
            'is_primary' => true,
        ];
    }
}
