<?php

declare(strict_types=1);

namespace Database\Factories\Support;

use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Support\ProductReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductReview>
 */
class ProductReviewFactory extends Factory
{
    protected $model = ProductReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'customer_id' => Customer::factory(),
            'store_id' => function (): int {
                $currency = Currency::query()->where(column: 'is_primary', operator: '=', value: true)->first()
                    ?? Currency::factory()->primary()->create();

                return Store::factory()->for($currency, 'currency')->create()->id;
            },
            'rating' => fake()->numberBetween(int1: 3, int2: 5),
            'comment' => fake()->paragraph(),
            'is_approved' => true,
            'reviewed_at' => now(),
        ];
    }

    public function approved(): self
    {
        return $this->state(fn () => ['is_approved' => true]);
    }

    public function pending(): self
    {
        return $this->state(fn () => ['is_approved' => false]);
    }
}
