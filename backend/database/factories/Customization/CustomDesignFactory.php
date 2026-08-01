<?php

namespace Database\Factories\Customization;

use App\Models\Catalog\Product;
use App\Models\Customization\CustomDesign;
use App\Models\Customization\ProductCustomization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomDesign>
 */
class CustomDesignFactory extends Factory
{
    protected $model = CustomDesign::class;

    public function definition(): array
    {
        return [
            'customer_id' => null,
            'user_id' => null,
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'product_customization_id' => ProductCustomization::factory(),
            'name' => fake()->words(3, true),
            'status' => fake()->randomElement(['draft', 'ready']),
            'background_color' => '#'.fake()->hexColor(),
            'canvas_width_px' => fake()->randomElement([800, 1200, 1600, 2400]),
            'canvas_height_px' => fake()->randomElement([800, 1200, 1600, 2400]),
            'canvas_settings' => null,
            'preview_url' => fake()->optional()->imageUrl(),
            'production_file_url' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function ready(): static
    {
        return $this->state(fn () => ['status' => 'ready']);
    }
}
