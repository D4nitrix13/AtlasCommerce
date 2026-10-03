<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder que crea el admin user por defecto para cada tienda.
 *
 * Convención de credenciales:
 *
 *   - email:    admin@<slug>.com   (ej. admin@panda-estampados.com)
 *   - password: password           (el primer login debe forzar el cambio)
 *
 * El seeder crea la tienda, los dominios (`<slug>.com`, `www.<slug>.com`,
 * `admin.<slug>.com`) y la asociación user↔store si no existen. Es
 * **idempotente**: corre múltiples veces sin duplicar datos. Todos los
 * registros usan `firstOrCreate` o `updateOrCreate`.
 *
 * Uso:
 *
 *   php artisan db:seed --class=DefaultAdminSeeder
 *
 * Para producción, se recomienda usar `tenants:bootstrap` con un JSON
 * por instancia en lugar de este seeder.
 */
final class DefaultAdminSeeder extends Seeder
{
    /**
     * Default password para todos los admins.
     *
     * SECURITY: este literal es solo para dev y testing. En producción
     * usar `BootstrapTenantCommand` con `password_env` apuntando a una
     * env var (PATRÓN: <TENANT>_ADMIN_PASSWORD) — ver
     * `docs/operations/default-admin-credentials.md`.
     *
     * El primer login debe forzar al usuario a cambiarlo (feature a
     * implementar; ver F-Login-Forced-Password-Change del roadmap).
     */
    private const DEFAULT_PASSWORD = 'password';

    /**
     * Slugs de las tiendas a las que este seeder les crea admin.
     *
     * Una sola tienda = un solo admin (el de esa tienda).
     * Si en el futuro un admin necesita gestionar múltiples tiendas,
     * se crea vía `tenants:bootstrap` con un JSON que liste varios slugs.
     *
     * @var array<int, string>
     */
    private const DEFAULT_TENANTS = [
        'panda-estampados',
        'kitsune',
        'mm-boutique',
        'morales-store',
    ];

    /**
     * Ejecuta el seeder.
     */
    public function run(): void
    {
        $currency = $this->ensurePrimaryCurrency();

        foreach (self::DEFAULT_TENANTS as $slug) {
            $store = $this->ensureStore($slug, $currency);
            $this->ensureDomains($store);
            $this->ensureAdminUser($store);
            $this->command?->info(sprintf(
                'DefaultAdminSeeder: %s listo (admin: admin@%s.com / %s).',
                $slug,
                $slug,
                self::DEFAULT_PASSWORD,
            ));
        }
    }

    /**
     * Asegura que exista la currency primaria (USD).
     */
    private function ensurePrimaryCurrency(): Currency
    {
        return Currency::query()->updateOrCreate(
            attributes: ['code' => 'USD'],
            values: [
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_primary' => true,
            ],
        );
    }

    /**
     * Asegura que exista la tienda con el slug dado.
     */
    private function ensureStore(string $slug, Currency $currency): Store
    {
        return Store::query()->updateOrCreate(
            attributes: ['slug' => $slug],
            values: [
                'name' => $this->humanize($slug),
                'currency_id' => $currency->id,
                'is_active' => true,
            ],
        );
    }

    /**
     * Crea los dominios canónicos (`<slug>.com`, `www.<slug>.com`,
     * `admin.<slug>.com`) si no existen.
     */
    private function ensureDomains(Store $store): void
    {
        $domains = [
            ['domain' => $store->slug.'.com', 'is_primary' => true],
            ['domain' => 'www.'.$store->slug.'.com', 'is_primary' => false],
            ['domain' => 'admin.'.$store->slug.'.com', 'is_primary' => false, 'is_admin' => true],
        ];

        foreach ($domains as $config) {
            StoreDomain::query()->updateOrCreate(
                attributes: ['store_id' => $store->id, 'domain' => $config['domain']],
                values: [
                    'type' => $config['is_admin'] ?? false ? 'subdomain' : 'domain',
                    'base_path' => '/',
                    'is_primary' => $config['is_primary'] ?? false,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Asegura que exista el admin user `admin@<slug>.com` con la
     * password default, asociado a la tienda.
     */
    private function ensureAdminUser(Store $store): User
    {
        $email = sprintf('admin@%s.com', $store->slug);

        $adminRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );

        $user = User::query()->updateOrCreate(
            attributes: ['email' => $email],
            values: [
                'first_name' => 'Admin',
                'last_name' => $this->humanize($store->slug),
                'password' => Hash::make(value: self::DEFAULT_PASSWORD),
                'is_active' => true,
                'role_id' => $adminRole->id,
            ],
        );

        $user->stores()->syncWithoutDetaching(ids: [$store->id]);

        return $user;
    }

    /**
     * Convierte `panda-estampados` → `Panda Estampados`.
     */
    private function humanize(string $slug): string
    {
        return ucwords(str_replace('-', ' ', $slug));
    }
}
