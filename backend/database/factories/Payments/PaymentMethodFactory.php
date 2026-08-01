<?php

namespace Database\Factories\Payments;

use App\Models\Payments\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    public function definition(): array
    {
        $code = fake()->unique()->bothify('method-####');

        return [
            'code' => $code,
            'name' => fake()->words(2, true),
            'provider' => null,
            'is_active' => true,
        ];
    }
}
