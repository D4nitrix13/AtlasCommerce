<?php

namespace Database\Factories\Inventory;

use App\Models\Catalog\Product;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'current_stock' => fake()->numberBetween(50, 500),
            'minimum_stock' => 10,
            'reserved_stock' => 0,
        ];
    }

    public function withStock(int $current, int $minimum = 0): static
    {
        return $this->state(state: fn (): array => [
            'current_stock' => $current,
            'minimum_stock' => $minimum,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(state: fn (): array => ['current_stock' => 0]);
    }
}
