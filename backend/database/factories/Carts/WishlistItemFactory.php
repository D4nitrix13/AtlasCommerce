<?php

declare(strict_types=1);

namespace Database\Factories\Carts;

use App\Models\Carts\Wishlist;
use App\Models\Carts\WishlistItem;
use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WishlistItem>
 */
class WishlistItemFactory extends Factory
{
    protected $model = WishlistItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wishlist_id' => Wishlist::factory(),
            'product_id' => Product::factory(),
            'added_at' => now(),
        ];
    }
}
