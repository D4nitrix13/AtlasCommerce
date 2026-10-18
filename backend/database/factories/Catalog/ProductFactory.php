<?php

declare(strict_types=1);

namespace Database\Factories\Catalog;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Settings\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'currency_id' => Currency::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-???')),
            // Phase 7 schema rich — los defaults reflejan las DB
            // defaults (false/false). Incluirlos en la factory evita
            // que los tests tengan que llamar a $model->fresh() para
            // leer los defaults aplicados por PostgreSQL.
            'is_exclusive' => false,
            'has_condition' => false,
            'has_sizes' => false,
            'base_price_cents' => fake()->numberBetween(500, 50_000),
            'cost_cents' => fake()->numberBetween(200, 10_000),
            'is_active' => true,
            'published_at' => now(),
        ];
    }
}
