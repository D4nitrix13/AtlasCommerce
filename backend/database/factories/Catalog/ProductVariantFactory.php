<?php

declare(strict_types=1);

namespace Database\Factories\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'product_id' => Product::factory(),
            'name' => $name,
            'sku' => strtoupper(fake()->unique()->bothify('VAR-####-???')),
            'price_cents' => fake()->numberBetween(800, 60_000),
            'cost_cents' => fake()->numberBetween(300, 15_000),
            'barcode' => fake()->unique()->numerify('############'),
            'is_active' => true,
        ];
    }
}
