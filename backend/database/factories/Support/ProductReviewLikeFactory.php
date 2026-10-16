<?php

declare(strict_types=1);

namespace Database\Factories\Support;

use App\Models\Customers\Customer;
use App\Models\Support\ProductReview;
use App\Models\Support\ProductReviewLike;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductReviewLike>
 */
class ProductReviewLikeFactory extends Factory
{
    protected $model = ProductReviewLike::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_review_id' => ProductReview::factory(),
            'customer_id' => Customer::factory(),
        ];
    }
}
