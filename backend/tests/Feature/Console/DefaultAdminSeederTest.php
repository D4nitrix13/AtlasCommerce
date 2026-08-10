<?php

declare(strict_types=1);
declare(strict_types=1);

namespace Tests\Feature\Console;

use Database\Seeders\DefaultAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del seeder `DefaultAdminSeeder`.
 *
 * Convención esperada:
 *   - admin@<slug>.com / password para cada tienda default
 *   - idempotente
 *   - asocia el admin a la tienda via user_stores
 */
#[Group('console')]
#[Group('seeders')]
#[Group('auth')]
final class DefaultAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Slugs de las tiendas que el seeder declara por default.
     *
     * @return array<int, string>
     */
    private const DEFAULT_TENANTS = ['panda-estampados', 'kitsune', 'mm-boutique', 'morales-store'];

    #[Test]
    public function seeder_creates_one_admin_per_default_tenant(): void
    {
        $this->seed(DefaultAdminSeeder::class);
        foreach (self::DEFAULT_TENANTS as $slug) {
            $this->assertDatabaseHas('users', ['email' => "admin@{$slug}.com", 'is_active' => true]);
        }
    }

    #[Test]
    public function seeder_uses_password_default_for_each_admin(): void
    {
        $this->seed(DefaultAdminSeeder::class);
        foreach (self::DEFAULT_TENANTS as $slug) {
            $user = \App\Models\Auth\User::query()->where(column: 'email', operator: '=', value: "admin@{$slug}.com")->firstOrFail();
            $this->assertTrue(Hash::check(value: 'password', hashedValue: $user->password), "El admin de {$slug} debe tener password 'password'.");
        }
    }

    #[Test]
    public function seeder_assigns_admin_role_to_each_user(): void
    {
        $this->seed(DefaultAdminSeeder::class);
        $adminRole = \App\Models\Auth\Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        foreach (self::DEFAULT_TENANTS as $slug) {
            $user = \App\Models\Auth\User::query()->where(column: 'email', operator: '=', value: "admin@{$slug}.com")->firstOrFail();
            $this->assertSame(expected: $adminRole->id, actual: $user->role_id);
            $this->assertTrue($user->hasPermission(slug: 'cualquier.permiso'));
        }
    }

    #[Test]
    public function seeder_associates_each_admin_to_its_store(): void
    {
        $this->seed(DefaultAdminSeeder::class);
        foreach (self::DEFAULT_TENANTS as $slug) {
            $store = \App\Models\Stores\Store::query()->where(column: 'slug', operator: '=', value: $slug)->firstOrFail();
            $user = \App\Models\Auth\User::query()->where(column: 'email', operator: '=', value: "admin@{$slug}.com")->firstOrFail();
            $this->assertTrue($user->hasAccessToStore(storeId: $store->id), "El admin de {$slug} debe estar asociado a su tienda.");
        }
    }

    #[Test]
    public function seeder_creates_domains_for_each_store(): void
    {
        $this->seed(DefaultAdminSeeder::class);
        foreach (self::DEFAULT_TENANTS as $slug) {
            $store = \App\Models\Stores\Store::query()->where(column: 'slug', operator: '=', value: $slug)->firstOrFail();
            $this->assertDatabaseHas('store_domains', ['store_id' => $store->id, 'domain' => "{$slug}.com", 'is_primary' => true]);
            $this->assertDatabaseHas('store_domains', ['store_id' => $store->id, 'domain' => "www.{$slug}.com"]);
            $this->assertDatabaseHas('store_domains', ['store_id' => $store->id, 'domain' => "admin.{$slug}.com"]);
        }
    }

    #[Test]
    public function seeder_is_idempotent_when_run_twice(): void
    {
        $this->seed(DefaultAdminSeeder::class);
        $this->seed(DefaultAdminSeeder::class);
        $this->assertSame(expected: count(self::DEFAULT_TENANTS), actual: \App\Models\Auth\User::query()->whereIn(column: 'email', values: array_map(fn (string $slug): string => "admin@{$slug}.com", self::DEFAULT_TENANTS))->count(), message: 'Re-correr el seeder no debe duplicar admins.');
        $this->assertSame(expected: count(self::DEFAULT_TENANTS), actual: \App\Models\Stores\Store::query()->count());
    }
}
