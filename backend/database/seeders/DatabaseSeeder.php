<?php

namespace Database\Seeders;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use Illuminate\Database\Seeder;

/**
 * Seeder mínimo que crea dos monedas base y dos tiendas con
 * tres categorías y un par de productos, suficiente para
 * `migrate:fresh --seed` y demos del storefront.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Currency::factory()->primary()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
        ]);

        Currency::factory()->create([
            'code' => 'NIO',
            'name' => 'Córdoba Nicaragüense',
            'symbol' => 'C$',
            'decimal_places' => 2,
        ]);

        $usd = Currency::firstWhere('code', 'USD');
        $nio = Currency::firstWhere('code', 'NIO');

        $storeA = Store::factory()->for(factory: $usd, relationship: 'currency')->create([
            'name' => 'Panda Estampados',
            'slug' => 'panda-estampados',
            'email' => 'panda@example.com',
        ]);

        Store::factory()->for(factory: $nio, relationship: 'currency')->create([
            'name' => 'Kitsune Store',
            'slug' => 'kitsune',
            'email' => 'kitsune@example.com',
        ]);

        $cat = Category::factory()->create(['name' => 'General', 'slug' => 'general']);

        Product::factory()
            ->for(factory: $cat, relationship: 'category')
            ->for(factory: $usd, relationship: 'currency')
            ->create([
                'name' => 'Demo Producto',
                'slug' => 'demo-producto',
                'sku' => 'DEMO-001',
                'base_price_cents' => 19_99,
            ]);

        // Catálogo de permisos + roles (admin, staff).
        $this->call(class: PermissionsCatalogSeeder::class);
    }
}
