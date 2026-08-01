<?php

namespace Database\Factories\Customization;

use App\Models\Customization\PrintArea;
use App\Models\Customization\ProductCustomization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrintArea>
 */
class PrintAreaFactory extends Factory
{
    protected $model = PrintArea::class;

    public function definition(): array
    {
        $widthPx = fake()->randomElement([800, 1200, 1600, 2400]);
        $heightPx = fake()->randomElement([800, 1200, 1600, 2400]);

        return [
            'product_customization_id' => ProductCustomization::factory(),
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->bothify('####-####'),
            'view' => fake()->randomElement(['front', 'back', 'wrap', 'left_sleeve', 'right_sleeve']),
            'width_inches' => fake()->randomFloat(2, 2, 12),
            'height_inches' => fake()->randomFloat(2, 2, 12),
            'width_px' => $widthPx,
            'height_px' => $heightPx,
            'dpi' => fake()->randomElement([150, 300, 600]),
            'position_x' => fake()->randomFloat(4, 0, 100),
            'position_y' => fake()->randomFloat(4, 0, 100),
            'relative_width' => fake()->optional()->randomFloat(4, 0.1, 1.0),
            'relative_height' => fake()->optional()->randomFloat(4, 0.1, 1.0),
            'mask' => null,
            'safe_zone' => null,
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
