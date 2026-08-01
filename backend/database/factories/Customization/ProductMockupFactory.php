<?php

namespace Database\Factories\Customization;

use App\Models\Customization\ProductCustomization;
use App\Models\Customization\ProductMockup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductMockup>
 */
class ProductMockupFactory extends Factory
{
    protected $model = ProductMockup::class;

    public function definition(): array
    {
        return [
            'product_customization_id' => ProductCustomization::factory(),
            'name' => fake()->unique()->words(2, true),
            'view' => fake()->randomElement(['front', 'back', 'wrap']),
            'mockup_type' => fake()->randomElement(['flat', 'transparent', 'ghost', 'model']),
            'base_image_url' => '/mockups/base/'.fake()->uuid().'.png',
            'mask_image_url' => fake()->optional()->passthrough('/mockups/mask/'.fake()->uuid().'.png'),
            'shadow_image_url' => fake()->optional()->passthrough('/mockups/shadow/'.fake()->uuid().'.png'),
            'render_settings' => null,
            'primary' => false,
            'active' => true,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['primary' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
