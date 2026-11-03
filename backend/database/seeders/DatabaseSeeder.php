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
        // Vocabulario de atributos por tipo de producto (capacidad en
        // onzas, talla de ropa, material, cuidados). Idempotente.
        $this->call(class: AttributeDefinitionSeeder::class);
        $this->call(class: RecoveryCodesTestSeeder::class);
        // CA.5 follow-up — customer con orders de ejemplo para que
        // el Account dashboard del frontend tenga data real al
        // hacer login como cliente (customer-auth sprint, commit 5
        // de 5). Ver docs/getting-started/credentials.md.
        $this->call(class: CustomerTestSeeder::class);

        // Catálogo demo (Oct 2026) — 16 guitarras eléctricas consolidadas
        // desde `database/seeds/products.json`: 4 líneas Fender AmPro
        // (Tele/Strat/Jaguar/VintageII 1957) + 10 Gibson Mod™ Collection
        // + 1 Custom Select 1968 + 1 Les Paul Supreme. Cada producto
        // describe su propio `image_pattern` para soportar las dos
        // convenciones (indexed vs keyword) que conviven en el recon
        // pipeline. Idempotente. Ver `ProductSeeder.php`.
        $this->call(class: ProductSeeder::class);
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
