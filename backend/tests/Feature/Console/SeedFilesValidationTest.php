<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifica que los archivos seeds/*.json de cada instancia estén bien
 * formados y pasen la validacion del comando tenants:bootstrap.
 *
 * Los archivos viven en `docker/instances/...` (RAÍZ del repo, fuera
 * del backend/). Dentro del container de dev solo se bind-mountea
 * `backend/`, por lo que estos archivos no son accesibles. En ese
 * caso los tests se skipean con un mensaje claro — para ejecutarlos
 * hay que correr `php artisan test` desde el host, no desde el
 * container.
 */
#[Group('console')]
#[Group('tenants')]
#[Group('seeds')]
final class SeedFilesValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Path al repo root desde el archivo de tests.
     *
     * `__DIR__` = `backend/tests/Feature/Console/`.
     *  - Host:   3 levels up → repo root (`backend/` parent).
     *  - Docker: 3 levels up también → `backend/` (que ES el repo root
     *           dentro del container — ver /var/www/html bind mount).
     *
     * Funciona en ambos contextos. Si los seed files no están
     * accesibles (no hay bind mount al repo root), los tests se
     * skipean automáticamente.
     */
    private const REPO_ROOT = __DIR__.'/../../..';

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function instanceProvider(): array
    {
        return [
            'panda-estampados-and-kitsune-dev' => ['development/panda-estampados-and-kitsune', 'dev-panda-estampados.json', 'DEV_ADMIN_PASSWORD'],
            'panda-estampados-and-kitsune-prod' => ['production/panda-estampados-and-kitsune', 'panda.json', 'PANDA_ADMIN_PASSWORD'],
            'mm-boutique-dev' => ['development/mm-boutique', 'dev-mm-boutique.json', 'DEV_ADMIN_PASSWORD'],
            'mm-boutique-prod' => ['production/mm-boutique', 'mm-boutique.json', 'MM_ADMIN_PASSWORD'],
            'morales-store-dev' => ['development/morales-store', 'dev-morales-store.json', 'DEV_ADMIN_PASSWORD'],
            'morales-store-prod' => ['production/morales-store', 'morales-store.json', 'MORALES_ADMIN_PASSWORD'],
        ];
    }

    private function seedPath(string $instanceFolder, string $filename): string
    {
        return self::REPO_ROOT."/docker/instances/{$instanceFolder}/seeds/{$filename}";
    }

    /**
     * Verifica que docker/instances/ sea accesible desde este contexto.
     * Si no, marca todos los tests como skipped (no falla).
     */
    private function requireDockerInstancesAccess(): void
    {
        if (! is_dir(self::REPO_ROOT.'/docker/instances')) {
            $this->markTestSkipped(sprintf(
                'docker/instances/ no accesible desde este contexto (path=%s). Estos tests requieren acceso al repo root — correr desde el host con `php artisan test`, no desde el container.',
                self::REPO_ROOT.'/docker/instances',
            ));
        }
    }

    #[Test]
    #[DataProvider('instanceProvider')]
    public function seed_file_exists_and_is_readable(string $instanceFolder, string $filename, string $passwordEnv): void
    {
        $this->requireDockerInstancesAccess();
        $path = $this->seedPath($instanceFolder, $filename);

        $this->assertFileExists($path, "Seed file not found: {$path}");
        $this->assertFileIsReadable($path, "Seed file not readable: {$path}");
    }

    #[Test]
    #[DataProvider('instanceProvider')]
    public function seed_file_has_valid_json(string $instanceFolder, string $filename, string $passwordEnv): void
    {
        $this->requireDockerInstancesAccess();
        $path = $this->seedPath($instanceFolder, $filename);
        $content = File::get($path);
        $decoded = json_decode($content, associative: true);

        $this->assertIsArray($decoded, "Seed file is not valid JSON: {$path}");
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'JSON decode error: '.json_last_error_msg());
    }

    #[Test]
    #[DataProvider('instanceProvider')]
    public function seed_file_contains_at_least_one_tenant(string $instanceFolder, string $filename, string $passwordEnv): void
    {
        $this->requireDockerInstancesAccess();
        $path = $this->seedPath($instanceFolder, $filename);
        $config = json_decode(File::get($path), associative: true);

        $this->assertArrayHasKey('tenants', $config);
        $this->assertNotEmpty($config['tenants'], 'Seed must have at least one tenant');
    }

    #[Test]
    #[DataProvider('instanceProvider')]
    public function seed_file_admin_email_follows_admin_at_slug_dot_com_pattern(string $instanceFolder, string $filename, string $passwordEnv): void
    {
        $this->requireDockerInstancesAccess();
        $path = $this->seedPath($instanceFolder, $filename);
        $config = json_decode(File::get($path), associative: true);
        $email = $config['admin_user']['email'];

        $this->assertMatchesRegularExpression(
            '/^admin@[a-z0-9-]+\.com$/',
            $email,
            "Admin email debe seguir el patrón admin@<slug>.com, encontré: {$email} en {$path}",
        );
    }

    #[Test]
    public function all_seed_files_can_be_processed_by_tenants_bootstrap(): void
    {
        $this->requireDockerInstancesAccess();
        foreach (self::instanceProvider() as [$instanceFolder, $filename, $passwordEnv]) {
            $path = $this->seedPath($instanceFolder, $filename);

            $tmpPath = tempnam(sys_get_temp_dir(), 'seed-');
            copy($path, $tmpPath);

            $config = json_decode(File::get($path), associative: true);
            if (isset($config['admin_user']['password_env'])) {
                putenv("{$passwordEnv}=test-password");
            }

            try {
                $this->artisan('tenants:bootstrap', ['path' => $tmpPath])
                    ->assertSuccessful();
            } finally {
                if (isset($config['admin_user']['password_env'])) {
                    putenv($passwordEnv);
                }
                @unlink($tmpPath);
            }
        }
    }

    #[Test]
    public function panda_seed_creates_two_tenants_and_admin_with_default_password(): void
    {
        $this->requireDockerInstancesAccess();
        $path = $this->seedPath('development/panda-estampados-and-kitsune', 'dev-panda-estampados.json');
        $tmpPath = tempnam(sys_get_temp_dir(), 'seed-');
        copy($path, $tmpPath);

        try {
            $this->artisan('tenants:bootstrap', ['path' => $tmpPath])
                ->assertSuccessful();

            $this->assertDatabaseCount(table: 'stores', count: 2);
            $this->assertDatabaseHas(table: 'stores', data: ['slug' => 'panda-estampados']);
            $this->assertDatabaseHas(table: 'stores', data: ['slug' => 'kitsune']);

            $this->assertDatabaseHas(table: 'users', data: [
                'email' => 'admin@panda-estampados.com',
                'is_active' => true,
            ]);

            $user = \App\Models\Auth\User::query()
                ->where('email', 'admin@panda-estampados.com')
                ->firstOrFail();
            $this->assertTrue(
                Hash::check(value: 'password', hashedValue: $user->password),
                'Admin de panda-estampados debe tener password default "password".',
            );
        } finally {
            @unlink($tmpPath);
        }
    }

    #[Test]
    public function mm_boutique_seed_creates_one_tenant_with_admin_at_slug_com(): void
    {
        $this->requireDockerInstancesAccess();
        $path = $this->seedPath('development/mm-boutique', 'dev-mm-boutique.json');
        $tmpPath = tempnam(sys_get_temp_dir(), 'seed-');
        copy($path, $tmpPath);

        try {
            $this->artisan('tenants:bootstrap', ['path' => $tmpPath])
                ->assertSuccessful();

            $this->assertDatabaseCount(table: 'stores', count: 1);
            $this->assertDatabaseHas(table: 'stores', data: ['slug' => 'mm-boutique']);
            $this->assertDatabaseHas(table: 'store_domains', data: ['domain' => 'mm-boutique.com']);
            $this->assertDatabaseHas(table: 'store_domains', data: ['domain' => 'www.mm-boutique.com']);
            $this->assertDatabaseHas(table: 'store_domains', data: ['domain' => 'admin.mm-boutique.com']);

            $this->assertDatabaseHas(table: 'users', data: [
                'email' => 'admin@mm-boutique.com',
            ]);
        } finally {
            @unlink($tmpPath);
        }
    }

    #[Test]
    public function morales_store_seed_creates_one_tenant_with_admin_at_slug_com(): void
    {
        $this->requireDockerInstancesAccess();
        $path = $this->seedPath('development/morales-store', 'dev-morales-store.json');
        $tmpPath = tempnam(sys_get_temp_dir(), 'seed-');
        copy($path, $tmpPath);

        try {
            $this->artisan('tenants:bootstrap', ['path' => $tmpPath])
                ->assertSuccessful();

            $this->assertDatabaseCount(table: 'stores', count: 1);
            $this->assertDatabaseHas(table: 'stores', data: ['slug' => 'morales-store']);
            $this->assertDatabaseHas(table: 'store_domains', data: ['domain' => 'morales-store.com']);
            $this->assertDatabaseHas(table: 'users', data: [
                'email' => 'admin@morales-store.com',
            ]);
        } finally {
            @unlink($tmpPath);
        }
    }

    #[Test]
    public function password_env_takes_priority_over_literal_password(): void
    {
        $json = [
            'tenants' => [
                [
                    'slug' => 'custom-store',
                    'name' => 'Custom Store',
                    'currency' => 'USD',
                    'domains' => ['custom-store.com'],
                ],
            ],
            'admin_user' => [
                'email' => 'admin@custom-store.com',
                'password_env' => 'CUSTOM_ADMIN_PASSWORD',
                'password' => 'should-be-ignored',
            ],
            'features' => [],
        ];

        $tmpPath = tempnam(sys_get_temp_dir(), 'seed-');
        file_put_contents($tmpPath, json_encode($json));
        putenv('CUSTOM_ADMIN_PASSWORD=from-env-var');

        try {
            $this->artisan('tenants:bootstrap', ['path' => $tmpPath])
                ->assertSuccessful();

            $user = \App\Models\Auth\User::query()
                ->where('email', 'admin@custom-store.com')
                ->firstOrFail();
            $this->assertTrue(Hash::check(value: 'from-env-var', hashedValue: $user->password));
            $this->assertFalse(Hash::check(value: 'should-be-ignored', hashedValue: $user->password));
            $this->assertFalse(Hash::check(value: 'password', hashedValue: $user->password));
        } finally {
            putenv('CUSTOM_ADMIN_PASSWORD');
            @unlink($tmpPath);
        }
    }

    #[Test]
    public function falls_back_to_default_password_when_neither_env_nor_literal_provided(): void
    {
        // Contrato actualizado: en APP_ENV=testing (default en CI), cae
        // al literal 'password' como fallback. En APP_ENV=production
        // este path falla con RuntimeException — ver test
        // throws_in_production_when_neither_password_env_nor_literal
        // en BootstrapTenantCommandTest, y docs/operations/default-admin-credentials.md.
        $json = [
            'tenants' => [
                [
                    'slug' => 'fallback-store',
                    'name' => 'Fallback Store',
                    'currency' => 'USD',
                    'domains' => ['fallback-store.com'],
                ],
            ],
            'admin_user' => [
                'email' => 'admin@fallback-store.com',
            ],
            'features' => [],
        ];

        $tmpPath = tempnam(sys_get_temp_dir(), 'seed-');
        file_put_contents($tmpPath, json_encode($json));

        try {
            $this->artisan('tenants:bootstrap', ['path' => $tmpPath])
                ->assertSuccessful();

            $user = \App\Models\Auth\User::query()
                ->where('email', 'admin@fallback-store.com')
                ->firstOrFail();
            $this->assertTrue(
                Hash::check(value: 'password', hashedValue: $user->password),
                'Sin password_env ni password literal, debe usar default "password" en dev/test.',
            );
        } finally {
            @unlink($tmpPath);
        }
    }
}
