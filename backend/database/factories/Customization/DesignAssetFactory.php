<?php

namespace Database\Factories\Customization;

use App\Models\Customization\DesignAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DesignAsset>
 */
class DesignAssetFactory extends Factory
{
    protected $model = DesignAsset::class;

    public function definition(): array
    {
        $extension = fake()->randomElement(['png', 'jpg', 'svg', 'webp']);

        return [
            'user_id' => null,
            'customer_id' => null,
            'original_name' => fake()->words(3, true).'.'.$extension,
            'file_name' => fake()->uuid().'.'.$extension,
            'mime_type' => 'image/'.$extension,
            'extension' => $extension,
            'storage_disk' => 'public',
            'file_path' => 'designs/'.fake()->uuid().'.'.$extension,
            'size_bytes' => fake()->numberBetween(min: 1024, max: 10485760),
            'width_px' => fake()->optional()->randomElement([800, 1200, 1600, 2400]),
            'height_px' => fake()->optional()->randomElement([800, 1200, 1600, 2400]),
            'dpi' => fake()->optional()->randomElement([72, 150, 300]),
            'checksum' => fake()->optional()->sha256(),
            'source' => fake()->randomElement(['upload', 'stock']),
            'status' => 'active',
        ];
    }

    public function uploaded(): static
    {
        return $this->state(fn () => ['source' => 'upload']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
