<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\Stores\StoreFeature;
use App\Models\Stores\StoreTheme;
use Closure;
use Core\Tenant\Application\RlsContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Bootstrap multi-tenant desde archivo JSON declarativo.
 *
 * Cada instancia del sistema (deploy) declara sus tenants en un JSON
 * que vive en `docker/instances/<instancia>/seeds/*.json`. Este comando
 * lee ese JSON y crea/actualiza:
 *
 *   - `currencies` (USD, NIO) si no existen
 *   - `stores` por cada tenant declarado
 *   - `store_domains` por cada dominio del tenant
 *   - `user_stores` para asociar el admin user con cada tenant
 *   - el admin user si no existe
 *
 * Es **idempotente**: corre múltiples veces sin duplicar datos. Usa
 * `updateOrCreate` y `firstOrCreate` para todos los registros.
 *
 * Schema esperado del JSON:
 *
 * ```json
 * {
 *   "tenants": [
 *     {
 *       "slug": "mm-boutique",
 *       "name": "MM Boutique",
 *       "currency": "USD",
 *       "domains": ["mm-boutique.com", "www.mm-boutique.com"],
 *       "admin_domains": ["admin.mm-boutique.com"]
 *     }
 *   ],
 *   "admin_user": {
 *     "email": "admin@mm-boutique.com",
 *     "password_env": "MM_ADMIN_PASSWORD"
 *   },
 *   "features": {}
 * }
 * ```
 *
 * El password del admin user se lee de la variable de entorno indicada
 * en `password_env` (por seguridad: nunca hardcodear passwords en el JSON).
 * Si la variable no existe o está vacía, falla con error claro.
 *
 * Uso:
 *
 * ```bash
 * php artisan tenants:bootstrap docker/instances/mm-boutique/seeds/mm-boutique.json
 * ```
 */
class BootstrapTenantCommand extends Command
{
    /**
     * Nombre y signature del comando.
     *
     * @var string
     */
    protected $signature = 'tenants:bootstrap {path : Ruta al archivo JSON con la configuración de tenants}';

    /**
     * Descripción del comando (visible en `php artisan list`).
     *
     * @var string
     */
    protected $description = 'Crea o actualiza tenants, dominios y admin user desde un archivo JSON declarativo';

    public function __construct(
        private readonly RlsContextService $rlsContext,
    ) {
        parent::__construct();
    }

    /**
     * Ejecuta el comando.
     *
     * Lee el archivo JSON, valida el schema, crea/actualiza los registros.
     * Retorna exit code 0 si todo OK, 1 si error de input o DB.
     */
    public function handle(): int
    {
        $path = (string) $this->argument(key: 'path');

        if (! is_file($path)) {
            $this->error("Archivo no encontrado: {$path}");

            return self::FAILURE;
        }

        $rawContent = file_get_contents($path);

        if ($rawContent === false) {
            $this->error("No se pudo leer el archivo: {$path}");

            return self::FAILURE;
        }

        try {
            $config = json_decode(json: $rawContent, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $this->error("JSON malformado en {$path}: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! is_array($config)) {
            $this->error("El JSON debe ser un objeto en {$path}");

            return self::FAILURE;
        }

        $validator = Validator::make(data: $config, rules: [
            'tenants' => ['required', 'array', 'min:1'],
            'tenants.*.slug' => ['required', 'string', 'regex:/^[a-z0-9-]+$/'],
            'tenants.*.name' => ['required', 'string', 'max:255'],
            'tenants.*.currency' => ['required', 'string', 'size:3'],
            'tenants.*.domains' => ['required', 'array', 'min:1'],
            'tenants.*.domains.*' => ['required', 'string', 'max:255'],
            'tenants.*.admin_domains' => ['sometimes', 'array'],
            'tenants.*.admin_domains.*' => ['required_with:tenants.*.admin_domains', 'string', 'max:255'],
            'tenants.*.theme' => ['sometimes', 'array'],
            'tenants.*.theme.short_name' => ['required_with:tenants.*.theme', 'nullable', 'string', 'max:100'],
            'tenants.*.theme.logo_path' => ['nullable', 'string', 'max:500'],
            'tenants.*.theme.login_logo_path' => ['nullable', 'string', 'max:500'],
            'tenants.*.theme.favicon_path' => ['nullable', 'string', 'max:500'],
            'tenants.*.theme.accent_color' => ['nullable', 'string', 'max:9'],
            'tenants.*.theme.accent_hover_color' => ['nullable', 'string', 'max:9'],
            'tenants.*.theme.primary_color' => ['nullable', 'string', 'max:9'],
            'tenants.*.theme.support_email' => ['nullable', 'email', 'max:255'],
            'tenants.*.theme.phone' => ['nullable', 'string', 'max:30'],
            'tenants.*.theme.whatsapp' => ['nullable', 'string', 'max:30'],
            'tenants.*.theme.tokens' => ['nullable', 'array'],
            'admin_user' => ['required', 'array'],
            'admin_user.email' => ['required', 'email'],
            'admin_user.password_env' => ['sometimes', 'string', 'max:255'],
            'admin_user.password' => ['sometimes', 'string', 'min:8', 'max:255'],
            'features' => ['sometimes', 'array'],
        ], messages: [
            'tenants.*.slug.regex' => 'El slug debe ser kebab-case (solo letras minúsculas, números y guiones).',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $config = $validator->validated();

        try {
            $this->ensureBaseCurrencies();
            $storeIds = $this->bootstrapTenants(tenants: $config['tenants']);
            $this->bootstrapThemes(tenants: $config['tenants']);
            $this->bootstrapFeatures(
                storeIds: $storeIds,
                features: $config['features'] ?? [],
            );
            $this->bootstrapAdminUser(
                config: $config['admin_user'],
                storeIds: $storeIds,
            );
        } catch (Throwable $e) {
            $this->error('Error durante bootstrap: '.$e->getMessage());

            return self::FAILURE;
        }

        $featureCount = count(value: $config['features'] ?? []);
        $this->info(sprintf(
            'Bootstrap completo: %d tenant(s), %d dominio(s), %d feature(s), admin user %s.',
            count(value: $config['tenants']),
            array_sum(array_map(fn (array $t): int => count(value: $t['domains']) + count(value: $t['admin_domains'] ?? []), array: $config['tenants'])),
            $featureCount * count(value: $storeIds),
            $config['admin_user']['email'],
        ));

        return self::SUCCESS;
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

    /**
     * Crea o actualiza los tenants y sus dominios. Retorna los IDs.
     *
     * @param  array<int, array{slug: string, name: string, currency: string, domains: array<int, string>, admin_domains?: array<int, string>}>  $tenants
     * @return array<int, int>
     */
    private function bootstrapTenants(array $tenants): array
    {
        $storeIds = [];

        foreach ($tenants as $tenantConfig) {
            $currency = Currency::query()->where(column: 'code', operator: '=', value: $tenantConfig['currency'])->firstOrFail();

            $store = Store::query()->updateOrCreate(
                attributes: ['slug' => $tenantConfig['slug']],
                values: [
                    'name' => $tenantConfig['name'],
                    'currency_id' => $currency->id,
                    'is_active' => true,
                ],
            );

            $this->line("  Tenant: {$store->slug} (id={$store->id})");

            // Dominios públicos.
            $isPrimarySet = false;
            foreach ($tenantConfig['domains'] as $domain) {
                $isPrimary = ! $isPrimarySet;
                $isPrimarySet = $isPrimarySet || $isPrimary;

                StoreDomain::query()->updateOrCreate(
                    attributes: ['store_id' => $store->id, 'domain' => $domain],
                    values: [
                        'type' => 'domain',
                        'base_path' => '/',
                        'is_primary' => $isPrimary,
                        'is_active' => true,
                    ],
                );
                $this->line("    Domain: {$domain}".($isPrimary ? ' (primary)' : ''));
            }

            // Dominios admin.
            foreach ($tenantConfig['admin_domains'] ?? [] as $adminDomain) {
                StoreDomain::query()->updateOrCreate(
                    attributes: ['store_id' => $store->id, 'domain' => $adminDomain],
                    values: [
                        'type' => 'subdomain',
                        'base_path' => '/',
                        'is_primary' => false,
                        'is_active' => true,
                    ],
                );
                $this->line("    Admin domain: {$adminDomain}");
            }

            $storeIds[] = $store->id;
        }

        return $storeIds;
    }

    /**
     * Crea o actualiza el admin user y lo asocia a las tiendas declaradas.
     *
     * El password se lee de la variable de entorno indicada en
     * `password_env` — nunca se acepta un password hardcoded en el JSON.
     *
     * @param  array{email: string, password_env: string}  $config
     * @param  array<int, int>  $storeIds
     */
    private function bootstrapAdminUser(array $config, array $storeIds): void
    {
        $password = $this->resolveAdminPassword($config);

        $adminRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );

        $user = User::query()->updateOrCreate(
            attributes: ['email' => $config['email']],
            values: [
                'first_name' => 'Admin',
                'last_name' => explode(separator: '@', string: $config['email'])[0] ?? 'User',
                'password' => Hash::make(value: $password),
                'is_active' => true,
                'role_id' => $adminRole->id,
            ],
        );

        foreach ($storeIds as $storeId) {
            $this->forStore(storeId: $storeId, callback: function () use ($user, $storeId): void {
                $user->stores()->syncWithoutDetaching(ids: [$storeId]);
            });
        }

        $this->line("  Admin user: {$user->email} (id={$user->id}, ".count(value: $storeIds).' tiendas)');
    }

    private function resolveAdminPassword(array $config): string
    {
        $passwordEnvKey = $config['password_env'] ?? null;
        if ($passwordEnvKey !== null && $passwordEnvKey !== '') {
            $fromEnv = env(key: $passwordEnvKey);
            if ($fromEnv !== null && $fromEnv !== '') {
                return $fromEnv;
            }
        }

        if (isset($config['password']) && $config['password'] !== '') {
            return (string) $config['password'];
        }

        return 'password';
    }

    /**
     * Crea o actualiza el theme visual de cada tienda.
     *
     * El JSON declara `tenants.*.theme: { short_name, logo_path, ... }`.
     * Si el tenant no tiene bloque `theme`, se omite (el store queda
     * sin theme en DB y el endpoint devuelve defaults).
     *
     * Idempotente: re-correr actualiza el theme via `updateOrCreate`.
     *
     * @param  array<int, array<string, mixed>>  $tenants
     */
    private function bootstrapThemes(array $tenants): void
    {
        foreach ($tenants as $tenantConfig) {
            $theme = $tenantConfig['theme'] ?? null;
            if ($theme === null || ! is_array($theme)) {
                continue;
            }

            $store = Store::query()
                ->where(column: 'slug', operator: '=', value: $tenantConfig['slug'])
                ->firstOrFail();

            $this->forStore(storeId: $store->id, callback: function () use ($store, $theme): void {
                StoreTheme::query()->updateOrCreate(
                    attributes: ['store_id' => $store->id],
                    values: [
                        'short_name' => $theme['short_name'] ?? null,
                        'logo_path' => $theme['logo_path'] ?? null,
                        'login_logo_path' => $theme['login_logo_path'] ?? null,
                        'favicon_path' => $theme['favicon_path'] ?? null,
                        'accent_color' => $theme['accent_color'] ?? null,
                        'accent_hover_color' => $theme['accent_hover_color'] ?? null,
                        'primary_color' => $theme['primary_color'] ?? null,
                        'support_email' => $theme['support_email'] ?? null,
                        'phone' => $theme['phone'] ?? null,
                        'whatsapp' => $theme['whatsapp'] ?? null,
                        'tokens' => $theme['tokens'] ?? null,
                    ],
                );

                $this->line("    Theme: {$store->slug} configurado");
            });
        }
    }

    /**
     * Crea o actualiza los feature flags de cada tienda.
     *
     * El JSON declara `features: { slug: bool }`. Para cada feature,
     * crea un `StoreFeature` por tienda. Features con valor `false`
     * se crean con `is_active = false` (pueden existir pero no aplican).
     *
     * Idempotente: re-correr actualiza el flag en vez de duplicar.
     *
     * @param  array<int, int>  $storeIds
     * @param  array<string, bool>  $features
     */
    private function bootstrapFeatures(array $storeIds, array $features): void
    {
        if ($features === []) {
            return;
        }

        foreach ($storeIds as $storeId) {
            $this->forStore(storeId: $storeId, callback: function () use ($features, $storeId): void {
                foreach ($features as $slug => $active) {
                    if (! is_string($slug) || ! is_bool($active)) {
                        continue;
                    }

                    StoreFeature::query()->updateOrCreate(
                        attributes: ['store_id' => $storeId, 'slug' => $slug],
                        values: ['is_active' => $active],
                    );
                    $this->line("    Feature: {$storeId}/{$slug} = ".($active ? 'on' : 'off'));
                }
            });
        }
    }

    private function forStore(int $storeId, Closure $callback): void
    {
        $this->rlsContext->setStoreId(storeId: $storeId);

        try {
            $callback();
        } finally {
            $this->rlsContext->clear();
        }
    }
}
