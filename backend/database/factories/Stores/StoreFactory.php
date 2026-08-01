<?php

namespace Database\Factories\Stores;

use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'currency_id' => Currency::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('########'),
            'address' => fake()->address(),
            'description' => fake()->sentence(),
            'primary_color' => '#111827',
            'secondary_color' => '#f97316',
            'tax_rate' => 0,
            'maintenance_mode' => false,
            'is_active' => true,
        ];
    }
}
