<?php

namespace Database\Factories\Shipping;

use App\Models\Shipping\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingZone>
 */
class ShippingZoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->city().' Zone',
            'description' => fake()->sentence(),
            'base_cost_cents' => fake()->numberBetween(200, 2000),
            'is_active' => true,
        ];
    }
}
