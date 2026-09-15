<?php

declare(strict_types=1);

namespace Database\Factories\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\WholesalePrice;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WholesalePrice>
 */
class WholesalePriceFactory extends Factory
{
    protected $model = WholesalePrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'store_id' => function (): int {
                $currency = Currency::query()->where(column: 'is_primary', operator: '=', value: true)->first()
                    ?? Currency::factory()->primary()->create();

                return Store::factory()->for($currency, 'currency')->create()->id;
            },
            'min_qty' => $this->faker->numberBetween(int1: 10, int2: 100),
            'price_cents' => $this->faker->numberBetween(int1: 1000, int2: 50_00),
        ];
    }
}
