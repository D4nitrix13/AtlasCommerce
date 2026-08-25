<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'name' => 'Almacén '.fake()->unique()->city(),
            'address' => fake()->streetAddress(),
            'phone' => fake()->e164PhoneNumber(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(state: fn (): array => ['is_active' => false]);
    }
}
