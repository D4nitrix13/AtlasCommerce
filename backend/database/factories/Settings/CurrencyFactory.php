<?php

declare(strict_types=1);

namespace Database\Factories\Settings;

use App\Models\Settings\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->words(2, true),
            'symbol' => '$',
            'decimal_places' => 2,
            'is_primary' => false,
            'is_active' => true,
        ];
    }

    public function primary(): self
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
