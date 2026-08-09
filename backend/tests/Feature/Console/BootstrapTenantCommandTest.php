<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Auth\User;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('console')]
#[Group('tenants')]
final class BootstrapTenantCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $tempJsonPath;

    /**
     * Crear archivo JSON temporal antes de cada test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tempJsonPath = sys_get_temp_dir().'/tenants-bootstrap-'.uniqid().'.json';
    }

    /**
     * Limpiar archivo JSON temporal después de cada test.
     */
    protected function tearDown(): void
    {
        if (is_file($this->tempJsonPath)) {
            unlink($this->tempJsonPath);
        }

        parent::tearDown();
    }

    /**
     * Helper: escribir el JSON al archivo temporal.
     *
     * @param  array<string, mixed>  $config
     */
    private function writeConfig(array $config): void
    {
        file_put_contents(
            $this->tempJsonPath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function it_creates_a_single_tenant_from_json(): void
    {
        $this->writeConfig([
            'tenants' => [
                [
                    'slug' => 'mm-boutique',
                    'name' => 'MM Boutique',
                    'currency' => 'USD',
                    'domains' => ['mm-boutique.com', 'www.mm-boutique.com'],
                    'admin_domains' => ['admin.mm-boutique.com'],
                ],
            ],
            'admin_user' => [
                'email' => 'admin@mm-boutique.com',
                'password_env' => 'MM_ADMIN_PASSWORD',
            ],
            'features' => [],
        ]);

        // Simular la variable de entorno con el password del admin.
        $this->app['config']->set('app.mm_admin_password', 'super-secret');

        // No podemos sobrescribir env() directamente, pero el comando
        // usa env() que lee de $_ENV / getenv(). Usamos putenv.
        putenv('MM_ADMIN_PASSWORD=super-secret');

        $this->artisan('tenants:bootstrap', ['path' => $this->tempJsonPath])
            ->assertSuccessful();

        // El tenant se crea.
        $this->assertDatabaseHas('stores', [
            'slug' => 'mm-boutique',
            'name' => 'MM Boutique',
            'is_active' => true,
        ]);

        // Los dominios públicos se crean.
        $this->assertDatabaseHas('store_domains', [
            'domain' => 'mm-boutique.com',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('store_domains', [
            'domain' => 'www.mm-boutique.com',
            'is_primary' => false,
            'is_active' => true,
        ]);

        // El dominio admin se crea.
        $this->assertDatabaseHas('store_domains', [
            'domain' => 'admin.mm-boutique.com',
            'type' => 'subdomain',
            'is_active' => true,
        ]);

        // El admin user se crea.
        $this->assertDatabaseHas('users', [
            'email' => 'admin@mm-boutique.com',
            'is_active' => true,
        ]);

        // El admin user queda asociado al tenant.
        $user = User::query()->where(column: 'email', operator: '=', value: 'admin@mm-boutique.com')->firstOrFail();
        $store = Store::query()->where(column: 'slug', operator: '=', value: 'mm-boutique')->firstOrFail();
        $this->assertTrue($user->stores->contains($store->id));

        putenv('MM_ADMIN_PASSWORD');
    }

    #[Test]
    public function it_creates_multiple_tenants_for_panda_kitsune_instance(): void
    {
        $this->writeConfig([
            'tenants' => [
                [
                    'slug' => 'panda-estampados',
                    'name' => 'Panda Estampados',
                    'currency' => 'NIO',
                    'domains' => ['panda-estampados.com'],
                    'admin_domains' => ['admin.panda-estampados.com'],
                ],
                [
                    'slug' => 'kitsune',
                    'name' => 'Kitsune Store',
                    'currency' => 'USD',
                    'domains' => ['kitsune.com'],
                    'admin_domains' => ['admin.kitsune.com'],
                ],
            ],
            'admin_user' => [
                'email' => 'admin@panda-estampados.test',
                'password_env' => 'PANDA_ADMIN_PASSWORD',
            ],
        ]);

        putenv('PANDA_ADMIN_PASSWORD=secret-123');

        $this->artisan('tenants:bootstrap', ['path' => $this->tempJsonPath])
            ->assertSuccessful();

        $this->assertDatabaseCount('stores', 2);
        $this->assertDatabaseHas('stores', ['slug' => 'panda-estampados']);
        $this->assertDatabaseHas('stores', ['slug' => 'kitsune']);

        // El admin user queda asociado a AMBAS tiendas.
        $user = User::query()->where(column: 'email', operator: '=', value: 'admin@panda-estampados.test')->firstOrFail();
        $this->assertSame(expected: 2, actual: $user->stores->count());

        putenv('PANDA_ADMIN_PASSWORD');
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $this->writeConfig([
            'tenants' => [
                [
                    'slug' => 'morales-store',
                    'name' => 'Morales Store',
                    'currency' => 'USD',
                    'domains' => ['morales-store.com'],
                    'admin_domains' => [],
                ],
            ],
            'admin_user' => [
                'email' => 'admin@morales-store.com',
                'password_env' => 'MORALES_ADMIN_PASSWORD',
            ],
        ]);

        putenv('MORALES_ADMIN_PASSWORD=first-password');

        // Correr 2 veces.
        $this->artisan('tenants:bootstrap', ['path' => $this->tempJsonPath])->assertSuccessful();
        $this->artisan('tenants:bootstrap', ['path' => $this->tempJsonPath])->assertSuccessful();

        // Solo hay 1 tenant, 1 dominio, 1 user.
        $this->assertSame(expected: 1, actual: Store::query()->count());
        $this->assertSame(expected: 1, actual: StoreDomain::query()->count());
        $this->assertSame(expected: 1, actual: User::query()->count());

        putenv('MORALES_ADMIN_PASSWORD');
    }

    #[Test]
    public function it_returns_failure_when_file_does_not_exist(): void
    {
        $this->artisan('tenants:bootstrap', [
            'path' => '/tmp/non-existent-'.uniqid().'.json',
        ])
            ->assertFailed();
    }

    #[Test]
    public function it_returns_failure_when_json_is_malformed(): void
    {
        file_put_contents($this->tempJsonPath, '{ invalid json');

        $this->artisan('tenants:bootstrap', ['path' => $this->tempJsonPath])
            ->assertFailed();
    }

    #[Test]
    public function it_returns_failure_when_schema_validation_fails(): void
    {
        // Falta el slug (campo required).
        $this->writeConfig([
            'tenants' => [
                [
                    'name' => 'Sin Slug',
                    'currency' => 'USD',
                    'domains' => ['test.com'],
                ],
            ],
            'admin_user' => [
                'email' => 'admin@test.com',
                'password_env' => 'TEST_PASSWORD',
            ],
        ]);

        $this->artisan('tenants:bootstrap', ['path' => $this->tempJsonPath])
            ->assertFailed();
    }

    #[Test]
    public function it_rejects_slug_with_invalid_characters(): void
    {
        $this->writeConfig([
            'tenants' => [
                [
                    'slug' => 'Slug Con Espacios',
                    'name' => 'Invalid Slug',
                    'currency' => 'USD',
                    'domains' => ['test.com'],
                ],
            ],
            'admin_user' => [
                'email' => 'admin@test.com',
                'password_env' => 'TEST_PASSWORD',
            ],
        ]);

        $this->artisan('tenants:bootstrap', ['path' => $this->tempJsonPath])
            ->assertFailed();
    }

    #[Test]
    public function it_returns_failure_when_password_env_is_not_set(): void
    {
        $this->writeConfig([
            'tenants' => [
                [
                    'slug' => 'no-password',
                    'name' => 'No Password',
                    'currency' => 'USD',
                    'domains' => ['no-password.com'],
                ],
            ],
            'admin_user' => [
                'email' => 'admin@no-password.com',
                'password_env' => 'NEVER_SET_PASSWORD',
            ],
        ]);

        // La variable NO está definida.
        putenv('NEVER_SET_PASSWORD');

        $this->artisan('tenants:bootstrap', ['path' => $this->tempJsonPath])
            ->assertFailed();
    }

    #[Test]
    public function it_ensures_base_currencies_exist(): void
    {
        $this->writeConfig([
            'tenants' => [
                [
                    'slug' => 'mm-boutique',
                    'name' => 'MM Boutique',
                    'currency' => 'USD',
                    'domains' => ['mm-boutique.com'],
                ],
            ],
            'admin_user' => [
                'email' => 'admin@mm-boutique.com',
                'password_env' => 'TEST_PASSWORD',
            ],
        ]);

        putenv('TEST_PASSWORD=test-123');

        $this->artisan('tenants:bootstrap', ['path' => $this->tempJsonPath])
            ->assertSuccessful();

        // USD y NIO se crean automáticamente.
        $this->assertDatabaseHas('currencies', ['code' => 'USD', 'is_primary' => true]);
        $this->assertDatabaseHas('currencies', ['code' => 'NIO']);

        putenv('TEST_PASSWORD');
    }

    #[Test]
    public function it_does_not_duplicate_domains_on_rerun(): void
    {
        $this->writeConfig([
            'tenants' => [
                [
                    'slug' => 'morales-store',
                    'name' => 'Morales Store',
                    'currency' => 'USD',
                    'domains' => ['morales-store.com', 'www.morales-store.com'],
                    'admin_domains' => ['admin.morales-store.com'],
                ],
            ],
            'admin_user' => [
                'email' => 'admin@morales-store.com',
                'password_env' => 'MORALES_PASSWORD',
            ],
        ]);

        putenv('MORALES_PASSWORD=secret');

        // Correr 3 veces.
        for ($i = 0; $i < 3; $i++) {
            $this->artisan('tenants:bootstrap', ['path' => $this->tempJsonPath])->assertSuccessful();
        }

        // Solo 3 dominios (2 publicos + 1 admin), no 9.
        $this->assertSame(expected: 3, actual: StoreDomain::query()->count());

        putenv('MORALES_PASSWORD');
    }

    #[Test]
    public function it_creates_theme_from_json_when_provided(): void
    {
        $this->writeConfig([
            'tenants' => [
                [
                    'slug' => 'panda-estampados',
                    'name' => 'Panda Estampados',
                    'currency' => 'NIO',
                    'domains' => ['panda-estampados.com'],
                    'theme' => [
                        'short_name' => 'Panda',
                        'logo_path' => '/images/panda/logo.webp',
                        'accent_color' => '#DC2626',
                        'accent_hover_color' => '#B91C1C',
                        'support_email' => 'support@panda-estampados.test',
                    ],
                ],
            ],
            'admin_user' => [
                'email' => 'admin@panda-estampados.test',
                'password_env' => 'PANDA_PASSWORD',
            ],
        ]);

        putenv('PANDA_PASSWORD=test');

        $this->artisan('tenants:bootstrap', ['path' => $this->tempJsonPath])
            ->assertSuccessful();

        $this->assertDatabaseHas('store_themes', [
            'short_name' => 'Panda',
            'logo_path' => '/images/panda/logo.webp',
            'accent_color' => '#DC2626',
            'accent_hover_color' => '#B91C1C',
            'support_email' => 'support@panda-estampados.test',
        ]);
    }

    #[Test]
    public function it_skips_theme_when_not_provided_in_json(): void
    {
        $this->writeConfig([
            'tenants' => [
                [
                    'slug' => 'no-theme',
                    'name' => 'No Theme',
                    'currency' => 'USD',
                    'domains' => ['no-theme.com'],
                ],
            ],
            'admin_user' => [
                'email' => 'admin@no-theme.com',
                'password_env' => 'NO_THEME_PASSWORD',
            ],
        ]);

        putenv('NO_THEME_PASSWORD=test');

        $this->artisan('tenants:bootstrap', ['path' => $this->tempJsonPath])
            ->assertSuccessful();

        // Sin theme en el JSON, no se crea StoreTheme.
        $this->assertDatabaseCount('store_themes', 0);

        putenv('NO_THEME_PASSWORD');
    }

    #[Test]
    public function it_creates_one_theme_per_tenant_for_panda_kitsune(): void
    {
        $this->writeConfig([
            'tenants' => [
                [
                    'slug' => 'panda-estampados',
                    'name' => 'Panda Estampados',
                    'currency' => 'NIO',
                    'domains' => ['panda-estampados.com'],
                    'theme' => [
                        'short_name' => 'Panda',
                        'accent_color' => '#DC2626',
                    ],
                ],
                [
                    'slug' => 'kitsune',
                    'name' => 'Kitsune Store',
                    'currency' => 'USD',
                    'domains' => ['kitsune.com'],
                    'theme' => [
                        'short_name' => 'Kitsune',
                        'accent_color' => '#7C3AED',
                    ],
                ],
            ],
            'admin_user' => [
                'email' => 'admin@panda-estampados.test',
                'password_env' => 'PK_PASSWORD',
            ],
        ]);

        putenv('PK_PASSWORD=test');

        $this->artisan('tenants:bootstrap', ['path' => $this->tempJsonPath])
            ->assertSuccessful();

        $this->assertDatabaseCount('store_themes', 2);
        $this->assertDatabaseHas('store_themes', ['short_name' => 'Panda', 'accent_color' => '#DC2626']);
        $this->assertDatabaseHas('store_themes', ['short_name' => 'Kitsune', 'accent_color' => '#7C3AED']);
    }

    #[Test]
    public function theme_is_idempotent_on_rerun(): void
    {
        $this->writeConfig([
            'tenants' => [
                [
                    'slug' => 'panda-estampados',
                    'name' => 'Panda Estampados',
                    'currency' => 'NIO',
                    'domains' => ['panda-estampados.com'],
                    'theme' => [
                        'short_name' => 'Panda',
                        'accent_color' => '#DC2626',
                    ],
                ],
            ],
            'admin_user' => [
                'email' => 'admin@panda-estampados.test',
                'password_env' => 'PANDA_PASSWORD',
            ],
        ]);

        putenv('PANDA_PASSWORD=test');

        // Correr 2 veces.
        $this->artisan('tenants:bootstrap', ['path' => $this->tempJsonPath])->assertSuccessful();
        $this->artisan('tenants:bootstrap', ['path' => $this->tempJsonPath])->assertSuccessful();

        // Un solo theme, no duplicado.
        $this->assertDatabaseCount('store_themes', 1);
    }
}
