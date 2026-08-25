<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Auth\User;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\InventoryMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        $previous = fake()->numberBetween(10, 100);
        $quantity = fake()->numberBetween(1, 10);

        return [
            'inventory_id' => Inventory::factory(),
            'user_id' => User::factory(),
            'movement_type' => 'sale',
            'quantity' => $quantity,
            'previous_stock' => $previous,
            'new_stock' => $previous - $quantity,
            'reason' => 'factory-seed',
            'movement_date' => now(),
        ];
    }

    public function ofType(string $type): static
    {
        return $this->state(state: fn (): array => ['movement_type' => $type]);
    }
}
