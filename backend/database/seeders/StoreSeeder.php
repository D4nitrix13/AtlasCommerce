<?php

namespace Database\Seeders;

use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\Stores\StoreSocialLink;
use Illuminate\Database\Seeder;

/**
 * Seeder que crea las dos tiendas del proyecto: Panda Estampados y Kitsune.
 *
 * Cada tienda tiene su dominio primario y redes sociales reales.
 * Los datos se leen de variables SEED_* en .env para facilitar
 * la personalización sin editar el seeder.
 */
class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $stores = [
            [
                'name' => env('SEED_PANDA_STORE_NAME', 'Panda Estampados'),
                'slug' => env('SEED_PANDA_STORE_SLUG', 'panda-estampados'),
                'logo' => env('SEED_PANDA_STORE_LOGO_PATH', 'images/stores/panda-estampados/brand/logo.webp'),
                'email' => env('SEED_PANDA_STORE_EMAIL', 'panda@example.com'),
                'primary_color' => '#111827',
                'secondary_color' => '#f97316',
                'domain' => env('SEED_PANDA_STORE_DOMAIN', 'panda-estampados.test'),
                'social' => [
                    ['platform' => 'instagram', 'url' => env('SEED_PANDA_STORE_INSTAGRAM_URL', 'https://www.instagram.com/pandaestampadosnic'), 'sort_order' => 0],
                    ['platform' => 'facebook', 'url' => env('SEED_PANDA_STORE_FACEBOOK_URL', 'https://www.facebook.com/pandaestampadosnic'), 'sort_order' => 1],
                ],
            ],
            [
                'name' => env('SEED_KITSUNE_STORE_NAME', 'Kitsune'),
                'slug' => env('SEED_KITSUNE_STORE_SLUG', 'kitsune'),
                'logo' => env('SEED_KITSUNE_STORE_LOGO_PATH', 'images/stores/kitsune/brand/logo.webp'),
                'email' => env('SEED_KITSUNE_STORE_EMAIL', 'kitsune@example.com'),
                'primary_color' => '#111827',
                'secondary_color' => '#f97316',
                'domain' => env('SEED_KITSUNE_STORE_DOMAIN', 'kitsune.test'),
                'social' => [
                    ['platform' => 'instagram', 'url' => env('SEED_KITSUNE_STORE_INSTAGRAM_URL', 'https://www.instagram.com/kitsunenic'), 'sort_order' => 0],
                    ['platform' => 'facebook', 'url' => env('SEED_KITSUNE_STORE_FACEBOOK_URL', 'https://www.facebook.com/kitsunenicaragua'), 'sort_order' => 1],
                ],
            ],
        ];

        foreach ($stores as $data) {
            $socialLinks = $data['social'];
            unset($data['social']);

            $domain = $data['domain'];
            unset($data['domain']);

            $store = Store::create($data);

            StoreDomain::create([
                'store_id' => $store->id,
                'domain' => $domain,
                'type' => 'domain',
                'base_path' => '/',
                'is_primary' => true,
                'is_active' => true,
            ]);

            foreach ($socialLinks as $social) {
                StoreSocialLink::create(array_merge($social, [
                    'store_id' => $store->id,
                    'is_active' => true,
                ]));
            }
        }
    }
}
