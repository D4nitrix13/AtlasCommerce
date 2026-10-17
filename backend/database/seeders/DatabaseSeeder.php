<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Settings\Currency;
use Illuminate\Database\Seeder;

/**
 * Seeder base del sistema.
 *
 * Solo crea **datos globales compartidos** (currencies, permissions,
 * roles). NO crea tenants, dominios, ni admin users — eso es
 * responsabilidad del comando `tenants:bootstrap` (ver F1.1 del
 * roadmap multi-instance).
 *
 * Cada instancia del sistema corre su propio `tenants:bootstrap` con
 * su JSON declarativo, en lugar de hardcodear tenants acá. Esto
 * permite que MM Boutique o Morales Store deployen sin arrastrar
 * tenants de Panda o Kitsune.
 *
 * Es **idempotente**: corre múltiples veces sin duplicar datos. Todos
 * los seeders que invoca usan `updateOrCreate` o `firstOrCreate`.
 *
 * Uso:
 *
 * ```bash
 * # Setup completo de una instancia:
 * php artisan migrate
 * php artisan db:seed          # Solo currencies + permissions
 * php artisan tenants:bootstrap seeds/mm-boutique.json   # tenants
 * ```
 *
 * Si querés un demo con Panda + Kitsune, ejecutá:
 *
 * ```bash
 * php artisan tenants:bootstrap docker/instances/panda-estampados/seeds/panda.json
 * ```
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Ejecuta el seeder.
     */
    public function run(): void
    {
        $this->ensureBaseCurrencies();
        $this->call(class: PermissionsCatalogSeeder::class);
        $this->call(class: RecoveryCodesTestSeeder::class);
        // CA.5 follow-up — customer con orders de ejemplo para que
        // el Account dashboard del frontend tenga data real al
        // hacer login como cliente (customer-auth sprint, commit 5
        // de 5). Ver docs/getting-started/credentials.md.
        $this->call(class: CustomerTestSeeder::class);

        // Catálogo demo — Fender Player Fusion Precision Bass HH con 2 variantes
        // (Ebony Black + Halo Metallic) y 10 imágenes WebP responsive.
        // Útil para validar la PDP con imágenes reales en lugar de SVG placeholder.
        // Las imágenes deben estar en frontend/public/images/products/...
        $this->call(class: FenderPlayerFusionBassSeeder::class);

        // Catálogo recon round 1 (Oct 2026) — 10 guitarras Gibson Mod™
        // Collection + Custom Select con imágenes square 1600x1600 reales.
        // Ver backend/database/seeders/GibsonModCollectionSeeder.php y
        // frontend/public/images/products/gibson-mod-collection-*/
        $this->call(class: GibsonModCollectionSeeder::class);

        // Catálogo recon round 1 (Oct 2026) — Fender American Professional
        // Telecaster, todas las 7 variantes (3-CS, Black, Natural, Oxblood,
        // Dark Night, Aged Cherry Burst, Butterscotch Blonde). Imágenes
        // landscape 1600x525; back image compartido por fingerboard.
        $this->call(class: FenderAmericanProfessionalTelecasterSeeder::class);

        // Catálogo recon round 1 (Oct 2026) — Fender American Professional
        // Stratocaster, 7 variantes (3-CS, Olympic White, Black, Oxblood,
        // Dark Night, 3-CS Maple, Olympic White Maple). Misma línea de
        // productos que el Tele, distinto body shape.
        $this->call(class: FenderAmericanProfessionalStratocasterSeeder::class);

        // Catálogo recon round 1 (Oct 2026) — Fender American Professional
        // Jaguar, 5 variants (3-CS, Olympic White, Black, Oxblood, Dark
        // Night). Distinto del Jazzmaster (el user confirmó Jaguar como
        // intención de las URLs). Scale 24" único + pickup Jaguar
        // single-coil + trémolo Panorama.
        $this->call(class: FenderAmericanProfessionalJaguarSeeder::class);

        // Catálogo recon round 1 (Oct 2026) — Fender American Vintage II
        // 1957 Stratocaster Black. Front específico del SKU, back compartido
        // entre colores del modelo.
        $this->call(class: FenderAmericanVintageII1957StratocasterSeeder::class);

        // Catálogo recon round 1 (Oct 2026) — Gibson Les Paul Supreme
        // Blueberry Burst (exclusive). Square 1600x1600.
        $this->call(class: GibsonLesPaulSupremeBlueberryBurstSeeder::class);
    }

    /**
     * Asegura que las currencies base (USD, NIO) existan.
     *
     * Idempotente: si ya existen, no hace nada.
     */
    private function ensureBaseCurrencies(): void
    {
        $defaults = [
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_primary' => true,
            ],
            [
                'code' => 'NIO',
                'name' => 'Córdoba Nicaragüense',
                'symbol' => 'C$',
                'decimal_places' => 2,
                'is_primary' => false,
            ],
        ];

        foreach ($defaults as $currency) {
            Currency::query()->updateOrCreate(
                attributes: ['code' => $currency['code']],
                values: $currency,
            );
        }
    }
}
