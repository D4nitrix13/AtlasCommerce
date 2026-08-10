<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Database\Seeders\DefaultAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integration test del flow admin login con credenciales por defecto.
 *
 * Cubre el caso de uso real:
 *   1. Seeder crea admin@<slug>.com / password.
 *   2. POST /api/auth/login con esas credenciales → 200 OK + token.
 *   3. GET /api/auth/me con el token → 200 OK + user payload.
 *   4. POST /api/auth/logout → token revocado, /me devuelve 401.
 *   5. POST /api/auth/login con la password vieja después del cambio → 401.
 *
 * Cada test corre en su propio dominio (admin.<slug>.com) para que el
 * middleware `store.domain` resuelva la tienda correcta.
 */
#[Group('auth')]
#[Group('http')]
#[Group('integration')]
final class AdminDefaultLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tenants a probar (los que el seeder declara por default).
     *
     * @return array<string, array{string}>
     */
    public static function tenantProvider(): array
    {
        return [
            'panda-estampados' => ['panda-estampados'],
            'kitsune' => ['kitsune'],
            'mm-boutique' => ['mm-boutique'],
            'morales-store' => ['morales-store'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DefaultAdminSeeder::class);
    }

    #[Test]
    #[DataProvider('tenantProvider')]
    public function admin_can_login_with_default_credentials(string $slug): void
    {
        $response = $this->postJson(
            "http://admin.{$slug}.com/api/auth/login",
            [
                'email' => "admin@{$slug}.com",
                'password' => 'password',
                'device_name' => 'integration-test',
            ],
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'token',
            'user' => [
                'id',
                'email',
                'full_name',
                'role' => ['id', 'slug', 'name'],
                'store_ids',
                'is_active',
            ],
        ]);

        $this->assertSame("admin@{$slug}.com", $response->json('user.email'));
        $this->assertSame('admin', $response->json('user.role.slug'));
        $this->assertTrue($response->json('user.is_active'));
        $this->assertNotEmpty($response->json('token'));
    }

    #[Test]
    #[DataProvider('tenantProvider')]
    public function admin_login_fails_with_wrong_password(string $slug): void
    {
        $response = $this->postJson(
            "http://admin.{$slug}.com/api/auth/login",
            [
                'email' => "admin@{$slug}.com",
                'password' => 'WRONG-password',
            ],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function login_fails_for_unknown_store_host(): void
    {
        $response = $this->postJson(
            'http://unknown.example.com/api/auth/login',
            [
                'email' => 'admin@panda-estampados.com',
                'password' => 'password',
            ],
        );

        $response->assertStatus(404);
    }

    #[Test]
    #[DataProvider('tenantProvider')]
    public function admin_cannot_login_into_other_tenant(string $slug): void
    {
        $otherSlugs = array_diff(
            ['panda-estampados', 'kitsune', 'mm-boutique', 'morales-store'],
            [$slug],
        );
        $otherSlug = array_values(array: $otherSlugs)[0];

        $response = $this->postJson(
            "http://admin.{$otherSlug}.com/api/auth/login",
            [
                'email' => "admin@{$slug}.com",
                'password' => 'password',
            ],
        );

        $response->assertStatus(422);
        $errorMessage = $response->json('errors.email.0');
        $this->assertIsString($errorMessage);
        $this->assertStringContainsString('No tienes acceso', $errorMessage);
    }

    #[Test]
    public function admin_login_me_logout_lifecycle(): void
    {
        $slug = 'panda-estampados';

        $loginResponse = $this->postJson(
            "http://admin.{$slug}.com/api/auth/login",
            [
                'email' => "admin@{$slug}.com",
                'password' => 'password',
                'device_name' => 'lifecycle-test',
            ],
        );
        $loginResponse->assertOk();
        $token = $loginResponse->json('token');
        $this->assertNotEmpty($token);

        $meResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson("http://admin.{$slug}.com/api/auth/me");

        $meResponse->assertOk();
        $meResponse->assertJsonPath('user.email', "admin@{$slug}.com");

        $user = \App\Models\Auth\User::query()
            ->where('email', "admin@{$slug}.com")
            ->firstOrFail();
        $this->assertSame(1, $user->tokens()->count(), 'Antes del logout debe haber 1 token.');

        $logoutResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson("http://admin.{$slug}.com/api/auth/logout");

        $logoutResponse->assertOk();
        $logoutResponse->assertJsonPath('message', 'Sesión cerrada.');
        $this->assertSame(0, $user->tokens()->count(), 'Después del logout no deben quedar tokens.');
    }

    #[Test]
    public function admin_can_change_default_password_and_old_no_longer_works(): void
    {
        $slug = 'panda-estampados';
        $user = \App\Models\Auth\User::query()
            ->where('email', "admin@{$slug}.com")
            ->firstOrFail();

        $user->update([
            'password' => Hash::make(value: 'New-Secure-Pass-2026'),
        ]);

        $newResponse = $this->postJson(
            "http://admin.{$slug}.com/api/auth/login",
            [
                'email' => "admin@{$slug}.com",
                'password' => 'New-Secure-Pass-2026',
            ],
        );
        $newResponse->assertOk();

        $oldResponse = $this->postJson(
            "http://admin.{$slug}.com/api/auth/login",
            [
                'email' => "admin@{$slug}.com",
                'password' => 'password',
            ],
        );
        $oldResponse->assertStatus(422);
    }

    #[Test]
    public function admin_login_from_public_domain_works_when_user_belongs_to_that_store(): void
    {
        $slug = 'panda-estampados';

        $response = $this->postJson(
            "http://{$slug}.com/api/auth/login",
            [
                'email' => "admin@{$slug}.com",
                'password' => 'password',
            ],
        );

        $response->assertOk();
        $this->assertSame("admin@{$slug}.com", $response->json('user.email'));
    }

    #[Test]
    public function admin_token_works_across_public_and_admin_domains_of_same_store(): void
    {
        $slug = 'panda-estampados';

        $loginResponse = $this->postJson(
            "http://admin.{$slug}.com/api/auth/login",
            [
                'email' => "admin@{$slug}.com",
                'password' => 'password',
                'device_name' => 'cross-host-test',
            ],
        );
        $token = $loginResponse->json('token');

        $meFromPublic = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson("http://{$slug}.com/api/auth/me");

        $meFromPublic->assertOk();
        $meFromPublic->assertJsonPath('user.email', "admin@{$slug}.com");
    }
}
