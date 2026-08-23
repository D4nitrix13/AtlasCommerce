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
