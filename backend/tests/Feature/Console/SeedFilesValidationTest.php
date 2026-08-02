<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifica que los archivos seeds/*.json de cada instancia estén bien
 * formados y pasen la validacion del comando tenants:bootstrap.
 *
 * Esto evita que un commit rompa los deployments por un JSON mal
 * formado o con schema invalido.
 */
#[Group('console')]
#[Group('tenants')]
#[Group('seeds')]
final class SeedFilesValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Path a la raiz del repo desde el directorio de tests.
     */
    private const REPO_ROOT = __DIR__.'/../../../..';

    /**
     * Instancias que deben tener archivo seeds/*.json.
     *
     * @return array<string, array{string, string}>
     */
    public static function instanceProvider(): array
    {
        return [
            'panda-kitsune' => ['panda-kitsune', 'panda.json'],
            'mm-boutique' => ['mm-boutique', 'mm-boutique.json'],
            'morales-store' => ['morales-store', 'morales-store.json'],
        ];
    }

    #[Test]
    #[DataProvider('instanceProvider')]
    public function seed_file_exists_and_is_readable(string $instance, string $filename): void
    {
        $path = self::REPO_ROOT."/docker/instances/{$instance}/seeds/{$filename}";

        $this->assertFileExists($path, "Seed file not found: {$path}");
        $this->assertFileIsReadable($path, "Seed file not readable: {$path}");
    }

    #[Test]
    #[DataProvider('instanceProvider')]
    public function seed_file_has_valid_json(string $instance, string $filename): void
    {
        $path = self::REPO_ROOT."/docker/instances/{$instance}/seeds/{$filename}";
        $content = File::get($path);
        $decoded = json_decode($content, associative: true);

        $this->assertIsArray($decoded, "Seed file is not valid JSON: {$path}");
        $this->assertNotNull(json_last_error_msg(), 'JSON decode error');
    }

    #[Test]
    #[DataProvider('instanceProvider')]
    public function seed_file_contains_at_least_one_tenant(string $instance, string $filename): void
    {
        $path = self::REPO_ROOT."/docker/instances/{$instance}/seeds/{$filename}";
        $config = json_decode(File::get($path), associative: true);

        $this->assertArrayHasKey('tenants', $config);
        $this->assertNotEmpty($config['tenants'], 'Seed must have at least one tenant');
    }

    #[Test]
    public function all_seed_files_can_be_processed_by_tenants_bootstrap(): void
    {
        foreach (self::instanceProvider() as [$instance, $filename]) {
            $path = self::REPO_ROOT."/docker/instances/{$instance}/seeds/{$filename}";

            // Copiar a un tmp file y setear el env var del password.
            $tmpPath = tempnam(sys_get_temp_dir(), 'seed-');
            copy($path, $tmpPath);

            $config = json_decode(File::get($path), associative: true);
            $passwordEnv = $config['admin_user']['password_env'];
            putenv("{$passwordEnv}=test-password");

            try {
                $this->artisan('tenants:bootstrap', ['path' => $tmpPath])
                    ->assertSuccessful();
            } finally {
                putenv($passwordEnv);
                @unlink($tmpPath);
            }
        }
    }

    #[Test]
    public function panda_seed_creates_two_tenants(): void
    {
        $path = self::REPO_ROOT.'/docker/instances/panda-kitsune/seeds/panda.json';
        $tmpPath = tempnam(sys_get_temp_dir(), 'seed-');
        copy($path, $tmpPath);
        putenv('PANDA_ADMIN_PASSWORD=test-123');

        try {
            $this->artisan('tenants:bootstrap', ['path' => $tmpPath])
                ->assertSuccessful();

            $this->assertDatabaseCount('stores', 2);
            $this->assertDatabaseHas('stores', ['slug' => 'panda-estampados']);
            $this->assertDatabaseHas('stores', ['slug' => 'kitsune']);
            $this->assertDatabaseCount('store_domains', 6);  // 2 + 2 + 1 + 1
        } finally {
            putenv('PANDA_ADMIN_PASSWORD');
            @unlink($tmpPath);
        }
    }

    #[Test]
    public function mm_boutique_seed_creates_one_tenant(): void
    {
        $path = self::REPO_ROOT.'/docker/instances/mm-boutique/seeds/mm-boutique.json';
        $tmpPath = tempnam(sys_get_temp_dir(), 'seed-');
        copy($path, $tmpPath);
        putenv('MM_ADMIN_PASSWORD=test-123');

        try {
            $this->artisan('tenants:bootstrap', ['path' => $tmpPath])
                ->assertSuccessful();

            $this->assertDatabaseCount('stores', 1);
            $this->assertDatabaseHas('stores', ['slug' => 'mm-boutique']);
            $this->assertDatabaseHas('store_domains', ['domain' => 'mm-boutique.com']);
            $this->assertDatabaseHas('store_domains', ['domain' => 'www.mm-boutique.com']);
            $this->assertDatabaseHas('store_domains', ['domain' => 'admin.mm-boutique.com']);
        } finally {
            putenv('MM_ADMIN_PASSWORD');
            @unlink($tmpPath);
        }
    }

    #[Test]
    public function morales_store_seed_creates_one_tenant(): void
    {
        $path = self::REPO_ROOT.'/docker/instances/morales-store/seeds/morales-store.json';
        $tmpPath = tempnam(sys_get_temp_dir(), 'seed-');
        copy($path, $tmpPath);
        putenv('MORALES_ADMIN_PASSWORD=test-123');

        try {
            $this->artisan('tenants:bootstrap', ['path' => $tmpPath])
                ->assertSuccessful();

            $this->assertDatabaseCount('stores', 1);
            $this->assertDatabaseHas('stores', ['slug' => 'morales-store']);
            $this->assertDatabaseHas('store_domains', ['domain' => 'morales-store.com']);
        } finally {
            putenv('MORALES_ADMIN_PASSWORD');
            @unlink($tmpPath);
        }
    }
}
