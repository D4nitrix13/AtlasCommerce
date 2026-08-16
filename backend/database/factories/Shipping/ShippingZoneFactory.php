<?php

declare(strict_types=1);

namespace Database\Factories\Shipping;

use App\Models\Shipping\ShippingZone;
use App\Models\Stores\Store;
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
            'store_id' => null,
        ];
    }

    /**
     * Asocia la zona a una tienda específica.
     *
     * Útil para tests multi-tenant que requieren zonas restringidas a
     * un único `store_id`. Sin este state, la zona queda global
     * (`store_id = NULL`), que es la representación por defecto del
     * modelo antes de la adopción masiva del trait `BelongsToStore`.
     */
    public function forStore(Store $store): static
    {
        return $this->state(state: fn (array $attributes): array => [
            'store_id' => $store->id,
        ]);
    }
}
