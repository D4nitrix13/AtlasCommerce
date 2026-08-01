<?php

namespace Database\Seeders;

use App\Models\Catalog\Product;
use App\Models\Customization\PrintArea;
use App\Models\Customization\ProductCustomization;
use App\Models\Customization\ProductMockup;
use Illuminate\Database\Seeder;

class ProductCustomizationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $product = Product::first();

        if (! $product) {
            return;
        }

        $customization = ProductCustomization::create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'customizable' => true,
            'customization_type' => 'print',
            'editor_type' => 'canvas',
            'status' => 'active',
            'instructions' => 'Upload high-resolution images for best print quality.',
        ]);

        PrintArea::create([
            'product_customization_id' => $customization->id,
            'name' => 'Front',
            'code' => 'front-01',
            'view' => 'front',
            'width_inches' => 10.00,
            'height_inches' => 10.00,
            'width_px' => 3000,
            'height_px' => 3000,
            'dpi' => 300,
            'position_x' => 0.0000,
            'position_y' => 0.0000,
            'relative_width' => 1.0000,
            'relative_height' => 1.0000,
            'active' => true,
        ]);

        ProductMockup::create([
            'product_customization_id' => $customization->id,
            'name' => 'Front Flat Mockup',
            'view' => 'front',
            'mockup_type' => 'flat',
            'base_image_url' => '/mockups/demo/front-flat.png',
            'primary' => true,
            'active' => true,
        ]);
    }
}
