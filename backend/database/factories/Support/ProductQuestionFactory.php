<?php

declare(strict_types=1);

namespace Database\Factories\Support;

use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Support\ProductQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductQuestion>
 */
class ProductQuestionFactory extends Factory
{
    protected $model = ProductQuestion::class;

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
            'question' => fake()->sentence(),
            'answer' => null,
            'asked_at' => now(),
            'answered_at' => null,
        ];
    }

    public function answered(): self
    {
        return $this->state(fn () => [
            'answer' => 'Porque está fabricado con algodón premium.',
            'answered_at' => now(),
        ]);
    }
}
