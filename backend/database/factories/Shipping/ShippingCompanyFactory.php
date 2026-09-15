<?php

declare(strict_types=1);

namespace Database\Factories\Shipping;

use App\Models\Shipping\ShippingCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingCompany>
 */
class ShippingCompanyFactory extends Factory
{
    protected $model = ShippingCompany::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => fake()->numerify('+## ########'),
            'email' => fake()->companyEmail(),
            'is_active' => true,
        ];
    }
}
