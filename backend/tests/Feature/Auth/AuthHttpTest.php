<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
#[Group('http')]
final class AuthHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Role $adminRole;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );

        // Tienda + dominio para que el middleware `store.domain` resuelve
        // contexto en los tests de auth. Usamos `localhost` que es el
        // host por defecto de TestCase.
        $currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($currency, 'currency')->create();
        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => 'localhost',
            'type' => 'domain',
            'base_path' => '/',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make(value: 'secret-password'),
            'is_active' => true,
            'role_id' => $this->adminRole->id,
        ]);

        // Asocia el user de tests a la tienda de tests.
        $this->user->stores()->attach($this->store->id);
    }

    #[Test]
    public function login_with_valid_credentials_returns_token_and_user(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'secret-password',
            'device_name' => 'jest-test',
        ]);

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
                'permissions',
            ],
        ]);

        $this->assertNotEmpty($response->json('token'));
        $this->assertSame('test@example.com', $response->json('user.email'));
        $this->assertSame('admin', $response->json('user.role.slug'));
        $this->assertTrue($response->json('user.is_active'));
        $this->assertSame([$this->store->id], $response->json('user.store_ids'));
    }

    #[Test]
    public function login_with_wrong_password_returns_422_with_validation_error(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function login_with_unknown_email_returns_422_with_validation_error(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'unknown@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function login_with_inactive_user_is_rejected(): void
    {
        $this->user->update(['is_active' => false]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.email.0', fn (string $m): bool => str_contains($m, 'desactivada'));
    }

    #[Test]
    public function login_validates_email_format(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'not-an-email',
            'password' => 'secret-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function login_validates_password_min_length(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function login_from_host_without_domain_returns_404(): void
    {
        // Host `unknown.example.com` no está en `store_domains`.
        $response = $this->postJson(
            uri: 'http://unknown.example.com/api/auth/login',
            data: [
                'email' => 'test@example.com',
                'password' => 'secret-password',
            ],
        );

        $response->assertStatus(404);
    }

    #[Test]
    public function login_from_store_user_does_not_belong_to_returns_422(): void
    {
        // Crear otra tienda con su dominio.
        $currency = Currency::factory()->primary()->create();
        $otherStore = Store::factory()->for($currency, 'currency')->create();
        StoreDomain::query()->create([
            'store_id' => $otherStore->id,
            'domain' => 'other.example.com',
            'type' => 'domain',
            'base_path' => '/',
            'is_active' => true,
            'is_primary' => true,
        ]);

        // User sigue siendo de $this->store, intenta entrar a otherStore.
        $response = $this->postJson(
            uri: 'http://other.example.com/api/auth/login',
            data: [
                'email' => 'test@example.com',
                'password' => 'secret-password',
            ],
        );

        $response->assertStatus(422);
        $response->assertJsonPath('errors.email.0', fn (string $m): bool => str_contains($m, 'No tienes acceso'));
    }

    #[Test]
    public function login_from_store_user_belongs_to_succeeds(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('token'));
    }

    #[Test]
    public function me_returns_authenticated_user(): void
    {
        Sanctum::actingAs($this->user, abilities: ['*']);

        $response = $this->getJson('/api/auth/me');

        $response->assertOk();
        $response->assertJsonPath('user.email', 'test@example.com');
        $response->assertJsonPath('user.role.slug', 'admin');
    }

    #[Test]
    public function me_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    #[Test]
    public function logout_revokes_current_token(): void
    {
        Sanctum::actingAs($this->user, abilities: ['*']);

        $response = $this->postJson('/api/auth/logout');

        $response->assertOk();
        $response->assertJsonPath('message', 'Sesión cerrada.');

        $tokenCount = $this->user->tokens()->count();
        $this->assertSame(0, $tokenCount);
    }

    #[Test]
    public function logout_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    #[Test]
    public function staff_user_permissions_are_listed(): void
    {
        $staffRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'staff'],
            values: ['name' => 'Operador', 'is_active' => true],
        );

        $permission = Permission::query()->updateOrCreate(
            attributes: ['slug' => 'shipping.shipments.ship'],
            values: ['name' => 'Ship Shipments', 'module' => 'shipping'],
        );

        $staffRole->permissions()->sync([$permission->id]);

        $staffUser = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => Hash::make(value: 'secret-password'),
            'is_active' => true,
            'role_id' => $staffRole->id,
        ]);

        $staffUser->stores()->attach($this->store->id);

        Sanctum::actingAs($staffUser, abilities: ['*']);

        $response = $this->getJson('/api/auth/me');

        $response->assertOk();
        $response->assertJsonPath('user.role.slug', 'staff');
        $response->assertJsonPath('user.permissions', ['shipping.shipments.ship']);
    }

    #[Test]
    public function register_endpoint_is_not_exposed(): void
    {
        // El admin NO tiene registro público. Los admins se crean
        // desde el seeder backend (admin@<slug>.com / password) o
        // desde el dashboard por otro admin via UserController.
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'new-admin@example.com',
            'password' => 'Secure-Pass-2026',
        ]);

        $response->assertStatus(404);
    }
}
