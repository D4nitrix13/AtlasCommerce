<?php

declare(strict_types=1);

namespace Database\Factories\Catalog;

use App\Models\Catalog\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'logo_url' => null,
            'is_active' => true,
        ];
    }
}
