<?php

namespace Database\Factories\Customization;

use App\Models\Customization\DesignFont;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DesignFont>
 */
class DesignFontFactory extends Factory
{
    protected $model = DesignFont::class;

    public function definition(): array
    {
        $family = fake()->randomElement(['Montserrat', 'Poppins', 'Roboto', 'Oswald', 'Inter']);

        return [
            'name' => $family.' '.fake()->randomElement(['Regular', 'Bold', 'Light']),
            'family' => $family,
            'style' => fake()->randomElement(['normal', 'italic']),
            'weight' => fake()->randomElement(['300', '400', '500', '600', '700']),
            'file_path' => fake()->optional()->passthrough('fonts/'.fake()->uuid().'.woff2'),
            'license' => fake()->optional()->randomElement(['OFL', 'Apache-2.0', 'proprietary']),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
