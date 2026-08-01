<?php

namespace Database\Factories\Customization;

use App\Models\Catalog\Product;
use App\Models\Customization\ProductCustomization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCustomization>
 */
class ProductCustomizationFactory extends Factory
{
    protected $model = ProductCustomization::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'customizable' => true,
            'customization_type' => fake()->randomElement(['print', 'engrave', 'embroidery']),
            'editor_type' => fake()->randomElement(['canvas', 'template']),
            'status' => 'active',
            'instructions' => fake()->optional()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function nonCustomizable(): static
    {
        return $this->state(fn () => ['customizable' => false]);
    }
}
