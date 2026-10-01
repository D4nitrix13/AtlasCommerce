<?php

declare(strict_types=1);

namespace Database\Factories\Account;

use App\Models\Account\BulkCartItem;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BulkCartItem>
 */
class BulkCartItemFactory extends Factory
{
    protected $model = BulkCartItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'quantity' => fake()->numberBetween(10, 500),
            'notes' => fake()->optional(weight: 0.4)->sentence(),
        ];
    }
}
