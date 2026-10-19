<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Seeder — Catálogo demo genérico.
 *
 * ⚠️ DEPRECATED: este seeder se mantiene solo por retrocompatibilidad con
 * `php artisan db:seed --class=ProductSeeder`. La lógica real de poblado
 * de catálogo está en `assets:seed-catalog` (artisan command), que:
 *
 *   1. Descubre imágenes en `assets/products/{tenant}/{brand}/{...}/`
 *   2. Hashea con `StorageKeyDeriver` (SHA-256 prefix 12)
 *   3. Sube a storage (local o FTP)
 *   4. Lee metadata rica de `data/catalog-meta/{cat}/{brand}/.../product.json`
 *   5. Puebla BD con productos, variants, images, product_stores
 *   6. Detecta is_exclusive, is_left_handed, handedness, primary image
 *
 * Este seeder DELEGA a `assets:seed-catalog` para evitar duplicación.
 * El `products.json` legacy con URLs hardcoded se mantiene por
 * retrocompatibilidad pero ya no se usa en runtime.
 *
 * @deprecated 2026-10-19 — usar `php artisan assets:seed-catalog` directamente.
 *
 * Migración recomendada:
 *   php artisan db:seed --class=DatabaseSeeder     # ya no llama a este
 *   php artisan assets:seed-catalog                 # nueva pipeline
 *
 * @see \App\Console\Commands\SeedCatalogAssetsCommand
 * @see \App\Services\Assets\CatalogAssetsSeeder
 * @see .ai/rules/assets-naming-policy.md
 */
final class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Delegar al nuevo seeder. Si la BD no tiene el tenant La Voz
        // configurado (es el único tenant con catalogo), se necesita
        // ejecutar primero `php artisan tenants:bootstrap` con el JSON
        // del tenant.
        Artisan::call('assets:seed-catalog', [
            '--tenant' => 'instrumentos-musicales-la-voz',
            '--asset-base' => base_path('../../assets'),
            '--backup-base' => base_path('../../data/catalog-meta'),
            '--storage-disk' => 'local',
        ]);
    }
}
