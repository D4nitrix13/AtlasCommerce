<?php

namespace Database\Factories\Customization;

use App\Models\Customization\DesignGraphic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DesignGraphic>
 */
class DesignGraphicFactory extends Factory
{
    protected $model = DesignGraphic::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'category' => fake()->randomElement(['shapes', 'icons', 'patterns', 'clipart', 'badges']),
            'tags' => fake()->words(3),
            'type' => fake()->randomElement(['svg', 'png', 'vector']),
            'file_url' => '/graphics/'.fake()->uuid().'.svg',
            'preview_url' => fake()->optional()->passthrough('/graphics/preview/'.fake()->uuid().'.png'),
            'license' => fake()->optional()->randomElement(['free', 'commercial', 'extended']),
            'free' => true,
            'active' => true,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['free' => false]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
