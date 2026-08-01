<?php

namespace Database\Factories\Customization;

use App\Models\Customization\CustomDesign;
use App\Models\Customization\DesignLayer;
use App\Models\Customization\PrintArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DesignLayer>
 */
class DesignLayerFactory extends Factory
{
    protected $model = DesignLayer::class;

    public function definition(): array
    {
        return [
            'custom_design_id' => CustomDesign::factory(),
            'print_area_id' => PrintArea::factory(),
            'design_asset_id' => null,
            'design_font_id' => null,
            'design_graphic_id' => null,
            'layer_type' => fake()->randomElement(['text', 'image', 'graphic']),
            'name' => fake()->optional()->words(2, true),
            'text_content' => fake()->optional()->sentence(),
            'color' => '#'.fake()->hexColor(),
            'background_color' => fake()->optional()->passthrough('#'.fake()->hexColor()),
            'alignment' => fake()->optional()->randomElement(['left', 'center', 'right']),
            'position_x' => fake()->randomFloat(4, 0, 500),
            'position_y' => fake()->randomFloat(4, 0, 500),
            'width' => fake()->optional()->randomFloat(4, 50, 500),
            'height' => fake()->optional()->randomFloat(4, 50, 500),
            'scale_x' => fake()->randomFloat(4, 0.5, 2.0),
            'scale_y' => fake()->randomFloat(4, 0.5, 2.0),
            'rotation' => fake()->randomFloat(3, 0, 360),
            'opacity' => fake()->randomFloat(4, 0.1, 1.0),
            'z_index' => fake()->numberBetween(0, 100),
            'locked' => false,
            'visible' => true,
            'styles' => null,
            'effects' => null,
        ];
    }

    public function text(): static
    {
        return $this->state(fn () => ['layer_type' => 'text']);
    }

    public function image(): static
    {
        return $this->state(fn () => ['layer_type' => 'image']);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['visible' => false]);
    }
}
