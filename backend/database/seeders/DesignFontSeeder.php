<?php

namespace Database\Seeders;

use App\Models\Customization\DesignFont;
use Illuminate\Database\Seeder;

class DesignFontSeeder extends Seeder
{
    public function run(): void
    {
        $fonts = [
            ['name' => 'Montserrat Regular', 'family' => 'Montserrat', 'style' => 'normal', 'weight' => '400', 'license' => 'OFL'],
            ['name' => 'Montserrat Bold', 'family' => 'Montserrat', 'style' => 'normal', 'weight' => '700', 'license' => 'OFL'],
            ['name' => 'Poppins Regular', 'family' => 'Poppins', 'style' => 'normal', 'weight' => '400', 'license' => 'OFL'],
            ['name' => 'Poppins SemiBold', 'family' => 'Poppins', 'style' => 'normal', 'weight' => '600', 'license' => 'OFL'],
            ['name' => 'Roboto Regular', 'family' => 'Roboto', 'style' => 'normal', 'weight' => '400', 'license' => 'Apache-2.0'],
            ['name' => 'Roboto Bold', 'family' => 'Roboto', 'style' => 'normal', 'weight' => '700', 'license' => 'Apache-2.0'],
            ['name' => 'Oswald Regular', 'family' => 'Oswald', 'style' => 'normal', 'weight' => '400', 'license' => 'OFL'],
            ['name' => 'Oswald Bold', 'family' => 'Oswald', 'style' => 'normal', 'weight' => '700', 'license' => 'OFL'],
            ['name' => 'Bebas Neue Regular', 'family' => 'Bebas Neue', 'style' => 'normal', 'weight' => '400', 'license' => 'OFL'],
            ['name' => 'Inter Regular', 'family' => 'Inter', 'style' => 'normal', 'weight' => '400', 'license' => 'OFL'],
            ['name' => 'Inter Medium', 'family' => 'Inter', 'style' => 'normal', 'weight' => '500', 'license' => 'OFL'],
            ['name' => 'Inter Bold', 'family' => 'Inter', 'style' => 'normal', 'weight' => '700', 'license' => 'OFL'],
        ];

        foreach ($fonts as $font) {
            DesignFont::updateOrCreate(
                attributes: ['family' => $font['family'], 'style' => $font['style'], 'weight' => $font['weight']],
                values: $font,
            );
        }
    }
}
