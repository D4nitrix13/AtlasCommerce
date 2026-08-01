<?php

namespace Database\Factories\Customization;

use App\Models\Customization\CustomDesign;
use App\Models\Customization\DesignMockupRender;
use App\Models\Customization\ProductMockup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DesignMockupRender>
 */
class DesignMockupRenderFactory extends Factory
{
    protected $model = DesignMockupRender::class;

    public function definition(): array
    {
        return [
            'custom_design_id' => CustomDesign::factory(),
            'product_mockup_id' => ProductMockup::factory(),
            'view' => fake()->randomElement(['front', 'back', 'wrap']),
            'rendered_image_url' => '/renders/'.fake()->uuid().'.png',
            'status' => 'ready',
            'metadata' => null,
            'rendered_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }
}
