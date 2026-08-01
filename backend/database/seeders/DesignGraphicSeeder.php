<?php

namespace Database\Seeders;

use App\Models\Customization\DesignGraphic;
use Illuminate\Database\Seeder;

class DesignGraphicSeeder extends Seeder
{
    public function run(): void
    {
        $graphics = [
            ['name' => 'Star Shape', 'category' => 'shapes', 'type' => 'svg', 'file_url' => '/graphics/shapes/star.svg', 'free' => true],
            ['name' => 'Circle Shape', 'category' => 'shapes', 'type' => 'svg', 'file_url' => '/graphics/shapes/circle.svg', 'free' => true],
            ['name' => 'Heart Shape', 'category' => 'shapes', 'type' => 'svg', 'file_url' => '/graphics/shapes/heart.svg', 'free' => true],
            ['name' => 'Arrow Icon', 'category' => 'icons', 'type' => 'svg', 'file_url' => '/graphics/icons/arrow.svg', 'free' => true],
            ['name' => 'Check Icon', 'category' => 'icons', 'type' => 'svg', 'file_url' => '/graphics/icons/check.svg', 'free' => true],
            ['name' => 'Flame Icon', 'category' => 'icons', 'type' => 'svg', 'file_url' => '/graphics/icons/flame.svg', 'free' => true],
            ['name' => 'Geometric Pattern', 'category' => 'patterns', 'type' => 'svg', 'file_url' => '/graphics/patterns/geometric.svg', 'free' => true],
            ['name' => 'Stripes Pattern', 'category' => 'patterns', 'type' => 'svg', 'file_url' => '/graphics/patterns/stripes.svg', 'free' => true],
            ['name' => 'Crown Badge', 'category' => 'badges', 'type' => 'svg', 'file_url' => '/graphics/badges/crown.svg', 'free' => true],
            ['name' => 'Shield Badge', 'category' => 'badges', 'type' => 'svg', 'file_url' => '/graphics/badges/shield.svg', 'free' => false],
        ];

        foreach ($graphics as $graphic) {
            DesignGraphic::updateOrCreate(
                attributes: ['name' => $graphic['name']],
                values: $graphic,
            );
        }
    }
}
