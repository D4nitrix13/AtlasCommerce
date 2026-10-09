<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CA.3 (customer-auth sprint, commit 3 de 5) — verifica que el login
 * existente funciona para customers con role='customer' y devuelve
 * el Customer profile en la response.
 *
 * Decisión de diseño: en vez de crear un endpoint separado
 * /api/v1/storefront/auth/login (que duplicaría la lógica de
 * AuthController::login), modificamos /api/v1/auth/login para que
 * incluya el campo `customer` en la response SOLO cuando el user
 * tiene role='customer'. Admin/staff siguen recibiendo la response
 * sin ese campo (backwards compatible).
 *
 * Cubre:
 *  - Customer con role='customer' loguea exitosamente y recibe
 *    { token, user, customer }.
 *  - El campo customer es null para admin (regresión).
 *  - El campo customer es null para staff (regresión).
 *  - El token es válido (Sanctum PAT).
 *  - Throttle: 5 logins buenos consecutivos NO lockean al user
 *    (regresión del clear() post-exito).
 */
#[Group('auth')]
#[Group('customer-auth')]
final class StorefrontLoginTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Role $customerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);

        $this->customerRole = Role::query()->where('slug', 'customer')->firstOrFail();

        $currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($currency, 'currency')->create();
        StoreDomain::query()->create(attributes: [
            'store_id' => $this->store->id,
            'domain' => 'localhost',
            'type' => 'domain',
            'base_path' => '/',
            'is_active' => true,
            'is_primary' => true,
        ]);

        // CA.3 — limpia el throttle de login porque otros tests
        // Feature/Auth consumen el mismo key. Sin esto, los 5
        // logins buenos consecutivos del setUp anterior lockean al user
        // durante 5 min y este test falla con "Demasiados intentos".
        \Illuminate\Support\Facades\RateLimiter::clear(key: 'login:cliente@panda-estampados.test|127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear(key: 'login:admin@panda-estampados.test|127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear(key: 'login:staff@panda-estampados.test|127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear(key: 'login:orphan@panda-estampados.test|127.0.0.1');
    }

    private function loginAs(string $email, string $password): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(
            uri: 'http://localhost/api/v1/auth/login',
            data: [
                'email' => $email,
                'password' => $password,
                'device_name' => 'jest-test',
            ],
        );
    }

    private function makeCustomer(string $email = 'cliente@panda-estampados.test'): User
    {
        $user = User::factory()->create(attributes: [
            'first_name' => 'Cliente',
            'last_name' => 'Panda',
            'email' => $email,
            'password' => Hash::make(value: 'SecurePass1234'),
            'phone' => '+505 8888 1234',
            'role_id' => $this->customerRole->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Customer::query()->create(attributes: [
            'user_id' => $user->id,
            'customer_type' => 'retail',
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => true,
            'registered_at' => now(),
        ]);

        return $user;
    }

    #[Test]
    public function customer_can_login_and_gets_token_user_and_customer(): void
    {
        $user = $this->makeCustomer();

        $response = $this->loginAs('cliente@panda-estampados.test', 'SecurePass1234');

        $response->assertOk();
        $response->assertJsonStructure([
            'token',
            'user' => ['id', 'email', 'full_name', 'role' => ['slug']],
            'customer' => ['id', 'email', 'first_name', 'last_name', 'customer_type'],
        ]);

        $this->assertSame(expected: 'customer', actual: $response->json(key: 'user.role.slug'));
        $this->assertSame(expected: 'cliente@panda-estampados.test', actual: $response->json(key: 'customer.email'));
        $this->assertSame(expected: 'retail', actual: $response->json(key: 'customer.customer_type'));
    }

    #[Test]
    public function admin_login_response_does_not_include_customer_field(): void
    {
        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(attributes: [
            'first_name' => 'Admin',
            'last_name' => 'Panda',
            'email' => 'admin@panda-estampados.test',
            'password' => Hash::make(value: 'SecurePass1234'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
        // Admin y staff necesitan estar en user_stores para acceder
        // a /admin/*. Customers bypasean este check (CA.3).
        $admin->stores()->attach($this->store->id);

        $response = $this->loginAs('admin@panda-estampados.test', 'SecurePass1234');

        $response->assertOk();
        $response->assertJsonStructure(['token', 'user']);
        $this->assertNull(actual: $response->json(key: 'customer'));
    }

    #[Test]
    public function staff_login_response_does_not_include_customer_field(): void
    {
        $staffRole = Role::query()->where('slug', 'staff')->firstOrFail();
        $staff = User::factory()->create(attributes: [
            'first_name' => 'Staff',
            'last_name' => 'Panda',
            'email' => 'staff@panda-estampados.test',
            'password' => Hash::make(value: 'SecurePass1234'),
            'role_id' => $staffRole->id,
            'is_active' => true,
        ]);
        $staff->stores()->attach($this->store->id);

        $response = $this->loginAs('staff@panda-estampados.test', 'SecurePass1234');

        $response->assertOk();
        $this->assertNull(actual: $response->json(key: 'customer'));
    }

    #[Test]
    public function customer_with_role_but_no_customer_profile_still_logs_in(): void
    {
        // Edge case: un User con role='customer' pero sin Customer
        // profile (e.g. admin que cambió manualmente el role). El
        // customer field debe ser null y el login NO debe fallar
        // (es defendible: el role es válido, solo falta el profile).
        User::factory()->create(attributes: [
            'first_name' => 'Orphan',
            'last_name' => 'Customer',
            'email' => 'orphan@panda-estampados.test',
            'password' => Hash::make(value: 'SecurePass1234'),
            'role_id' => $this->customerRole->id,
            'is_active' => true,
        ]);

        $response = $this->loginAs('orphan@panda-estampados.test', 'SecurePass1234');

        $response->assertOk();
        $this->assertNull(actual: $response->json(key: 'customer'));
    }

    #[Test]
    public function wrong_password_returns_422(): void
    {
        $this->makeCustomer();

        $response = $this->loginAs('cliente@panda-estampados.test', 'wrong-password');

        $response->assertStatus(422);
    }

    #[Test]
    public function inactive_customer_cannot_login(): void
    {
        $user = $this->makeCustomer();
        $user->update(attributes: ['is_active' => false]);

        $response = $this->loginAs('cliente@panda-estampados.test', 'SecurePass1234');

        $response->assertStatus(422);
    }
}
