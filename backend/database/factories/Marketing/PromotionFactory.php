<?php

declare(strict_types=1);

namespace Database\Factories\Marketing;

use App\Models\Catalog\Product;
use App\Models\Marketing\Promotion;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => null,
            'name' => fake()->unique()->sentence(nbWords: 3),
            'description' => fake()->paragraph(),
            'discount_type' => 'percentage',
            'value' => 10.0,
            'starts_at' => now()->subDays(7)->toDateString(),
            'ends_at' => now()->addDays(30)->toDateString(),
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

    public function forStore(Store $store): self
    {
        return $this->state(fn () => ['store_id' => $store->id]);
    }

    public function withProduct(Product $product): self
    {
        return $this->afterCreating(function (Promotion $promotion) use ($product): void {
            $promotion->products()->attach($product->id);
        });
    }
}
