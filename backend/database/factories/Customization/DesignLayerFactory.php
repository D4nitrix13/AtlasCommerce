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
            'position_x' => fake()->randomFloat(nbMaxDecimals: 4, min: 0, max: 500),
            'position_y' => fake()->randomFloat(nbMaxDecimals: 4, min: 0, max: 500),
            'width' => fake()->optional()->randomFloat(nbMaxDecimals: 4, min: 50, max: 500),
            'height' => fake()->optional()->randomFloat(nbMaxDecimals: 4, min: 50, max: 500),
            'scale_x' => fake()->randomFloat(nbMaxDecimals: 4, min: 0.5, max: 2.0),
            'scale_y' => fake()->randomFloat(nbMaxDecimals: 4, min: 0.5, max: 2.0),
            'rotation' => fake()->randomFloat(nbMaxDecimals: 3, min: 0, max: 360),
            'opacity' => fake()->randomFloat(nbMaxDecimals: 4, min: 0.1, max: 1.0),
            'z_index' => fake()->numberBetween(min: 0, max: 100),
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
