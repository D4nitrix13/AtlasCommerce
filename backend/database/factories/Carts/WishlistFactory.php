<?php

declare(strict_types=1);

namespace Database\Factories\Carts;

use App\Models\Carts\Wishlist;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wishlist>
 */
class WishlistFactory extends Factory
{
    protected $model = Wishlist::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'store_id' => function (): int {
                $currency = Currency::query()->where(column: 'is_primary', operator: '=', value: true)->first()
                    ?? Currency::factory()->primary()->create();

                return Store::factory()->for($currency, 'currency')->create()->id;
            },
            'name' => 'Mi lista',
            'is_default' => true,
        ];
    }

    public function default(): self
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
