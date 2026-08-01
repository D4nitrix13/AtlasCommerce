<?php

namespace Database\Factories\Marketing;

use App\Models\Marketing\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => null,
            'code' => strtoupper(fake()->unique()->bothify('PROMO##??')),
            'discount_type' => 'percentage',
            'value' => 10.0,
            'minimum_amount' => 0,
            'starts_at' => now()->subDays(7)->toDateString(),
            'ends_at' => now()->addDays(30)->toDateString(),
            'usage_limit' => 100,
            'used_count' => 0,
            'is_active' => true,
        ];
    }

    public function percentage(float $value): self
    {
        return $this->state(fn () => ['discount_type' => 'percentage', 'value' => $value]);
    }

    public function fixed(int $cents): self
    {
        return $this->state(fn () => ['discount_type' => 'fixed', 'value' => $cents / 100]);
    }
}
