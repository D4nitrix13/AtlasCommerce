<?php

declare(strict_types=1);

namespace Database\Factories\Customers;

use App\Models\Customers\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('##########'),
            'customer_type' => 'retail',
            'identity_document' => null,
            'is_active' => true,
        ];
    }
}
