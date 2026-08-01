<?php

namespace Database\Factories\Stores;

use App\Models\Stores\SalesChannel;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesChannel>
 */
class SalesChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => fake()->randomElement(['Web', 'POS', 'MercadoLibre', 'WhatsApp']),
            'type' => fake()->randomElement(['online', 'physical', 'marketplace']),
            'is_active' => true,
        ];
    }
}
