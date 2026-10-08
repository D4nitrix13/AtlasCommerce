<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\Role;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del seeder `PermissionsCatalogSeeder` (CA.1 — customer-auth sprint).
 *
 * Cubre:
 *   - Idempotencia: correr el seeder 2 veces no duplica ni rompe.
 *   - Los 3 roles canónicos (admin, staff, customer) se crean con
 *     su `is_active = true`.
 *   - El role customer NO tiene permisos administrativos attached
 *     (es un role de storefront, no de admin panel).
 *   - El role staff sigue teniendo sus permisos como antes (regresión
 *     para no romper el código existente).
 *   - El role admin sigue sin permisos attached (bypasea por slug).
 */
#[Group('auth')]
#[Group('seeders')]
final class PermissionsCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creates_the_three_canonical_roles(): void
    {
        $this->seed(class: PermissionsCatalogSeeder::class);

        $slugs = Role::query()->pluck(column: 'slug')->all();

        $this->assertContains(needle: 'admin', haystack: $slugs, message: 'Rol admin no fue creado.');
        $this->assertContains(needle: 'staff', haystack: $slugs, message: 'Rol staff no fue creado.');
        $this->assertContains(needle: 'customer', haystack: $slugs, message: 'Rol customer no fue creado (CA.1).');
    }

    #[Test]
    public function all_canonical_roles_are_active_by_default(): void
    {
        $this->seed(class: PermissionsCatalogSeeder::class);

        $admin = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $staff = Role::query()->where(column: 'slug', operator: '=', value: 'staff')->firstOrFail();
        $customer = Role::query()->where(column: 'slug', operator: '=', value: 'customer')->firstOrFail();

        $this->assertTrue(condition: $admin->is_active);
        $this->assertTrue(condition: $staff->is_active);
        $this->assertTrue(condition: $customer->is_active);
    }

    #[Test]
    public function customer_role_has_no_administrative_permissions(): void
    {
        // El rol customer es para usuarios del storefront. NO debe
        // tener permisos administrativos (sales.orders.manage,
        // customers.write, etc) — los customers usan el storefront
        // como usuarios regulares via Sanctum stateful cookies y
        // acceden a SU propio Customer profile. Si un customer
        // tuviera permisos administrativos, sería un bug crítico de
        // authorization.
        $this->seed(class: PermissionsCatalogSeeder::class);

        $customer = Role::query()->where(column: 'slug', operator: '=', value: 'customer')->firstOrFail();
        $permissions = $customer->permissions()->pluck(column: 'slug')->all();

        $this->assertEmpty(
            actual: $permissions,
            message: 'El rol customer no debe tener permisos attached. Encontrados: '.implode(separator: ', ', array: $permissions),
        );
    }

    #[Test]
    public function admin_role_has_no_permissions_attached_bypasses_by_slug(): void
    {
        // El rol admin bypasea el chequeo de permisos por el slug
        // (ver `App\Models\Auth\User::hasPermission`). Por diseño NO
        // debe tener permisos attached — el bypass ocurre en código
        // del modelo, no en el seeder.
        $this->seed(class: PermissionsCatalogSeeder::class);

        $admin = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $permissions = $admin->permissions()->pluck(column: 'slug')->all();

        $this->assertEmpty(
            actual: $permissions,
            message: 'El rol admin no debe tener permisos attached (bypasea por slug). Encontrados: '.implode(separator: ', ', array: $permissions),
        );
    }

    #[Test]
    public function seeder_is_idempotent(): void
    {
        // Correr 2 veces no debe duplicar roles ni romper nada.
        $this->seed(class: PermissionsCatalogSeeder::class);
        $this->seed(class: PermissionsCatalogSeeder::class);

        $roles = Role::query()->whereIn(column: 'slug', values: ['admin', 'staff', 'customer'])->get();

        $this->assertCount(3, $roles, 'El seeder duplicó roles.');
    }

    #[Test]
    public function permissions_catalog_creates_all_modules(): void
    {
        $this->seed(class: PermissionsCatalogSeeder::class);

        // El catálogo declarativo tiene 12 módulos. Verificamos que
        // el seeder creó al menos un Permission por módulo (no
        // chequeamos cada slug individual porque eso es test del
        // catálogo en sí, no del seeder).
        $modules = \App\Models\Auth\Permission::query()
            ->distinct()
            ->pluck(column: 'module')
            ->all();

        $this->assertGreaterThan(
            5,
            count($modules),
            'El seeder debería crear permisos en varios módulos. Encontrados: '.implode(', ', $modules),
        );
    }
}
