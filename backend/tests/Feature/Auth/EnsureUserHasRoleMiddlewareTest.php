<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cubre `App\Http\Middleware\EnsureUserHasRole` (alias `role`).
 *
 * El middleware se aplica a TODO el namespace `/api/v1/admin/*` (ver
 * `routes/api.php`). Su trabajo es la primera línea de defensa: solo
 * usuarios con `role.slug` en la lista permitida pueden entrar a una
 * ruta admin. Los chequeos finos por permiso siguen delegados al
 * middleware `permission:` existente.
 *
 * Estrategia de test: registramos una ruta efímera `__test_role_gate__`
 * que solo monta el middleware a evaluar (sin store.domain ni
 * controllers complejos) — eso nos permite probar el gateo puro sin
 * armar fixtures de tenant/orders. El path no aparece en producción.
 */
#[Group('auth')]
#[Group('rbac')]
final class EnsureUserHasRoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );

        $this->staffRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'staff'],
            values: ['name' => 'Operador', 'is_active' => true],
        );

        // Ruta efímera con `role:admin` (la firma más común).
        Route::get(
            uri: '/api/v1/__test_role_gate__',
            action: fn () => response()->json(data: ['ok' => true]),
        )->middleware(['auth:sanctum', 'role:admin']);

        // Ruta efímera con `role:admin,super-admin` (firma multi-slug).
        Route::get(
            uri: '/api/v1/__test_role_multi__',
            action: fn () => response()->json(data: ['ok' => true]),
        )->middleware(['auth:sanctum', 'role:admin,super-admin']);
    }

    #[Test]
    public function admin_role_passes_role_admin_gate(): void
    {
        $admin = User::factory()->create(attributes: [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
        Sanctum::actingAs(user: $admin);

        $response = $this->getJson(uri: '/api/v1/__test_role_gate__');
        $response->assertOk();
        $response->assertJsonPath('ok', true);
    }

    #[Test]
    public function staff_role_is_forbidden_on_role_admin_gate(): void
    {
        $staff = User::factory()->create(attributes: [
            'role_id' => $this->staffRole->id,
            'is_active' => true,
        ]);
        Sanctum::actingAs(user: $staff);

        $response = $this->getJson(uri: '/api/v1/__test_role_gate__');
        $response->assertStatus(403);
        $response->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'rol autorizado'));
    }

    #[Test]
    public function user_without_role_is_forbidden(): void
    {
        $noRole = User::factory()->create(attributes: [
            'role_id' => null,
            'is_active' => true,
        ]);
        Sanctum::actingAs(user: $noRole);

        $response = $this->getJson(uri: '/api/v1/__test_role_gate__');
        $response->assertStatus(403);
    }

    #[Test]
    public function inactive_user_is_forbidden_even_with_admin_role(): void
    {
        $admin = User::factory()->create(attributes: [
            'role_id' => $this->adminRole->id,
            'is_active' => false,
        ]);
        Sanctum::actingAs(user: $admin);

        $response = $this->getJson(uri: '/api/v1/__test_role_gate__');
        $response->assertStatus(403);
        $response->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'desactivada'));
    }

    #[Test]
    public function user_with_inactive_role_is_forbidden(): void
    {
        $this->adminRole->update(attributes: ['is_active' => false]);

        $admin = User::factory()->create(attributes: [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
        Sanctum::actingAs(user: $admin);

        $response = $this->getJson(uri: '/api/v1/__test_role_gate__');
        $response->assertStatus(403);
        $response->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'Rol desactivado'));
    }

    #[Test]
    public function unauthenticated_request_returns_401(): void
    {
        // Sin token Sanctum. La auth:sanctum debe rechazar primero;
        // el role:admin es defensivo pero NO debe opacar ese 401.
        $response = $this->getJson(uri: '/api/v1/__test_role_gate__');
        $response->assertStatus(401);
    }

    #[Test]
    public function comma_separated_slugs_accept_any_of_them(): void
    {
        // Cubre la firma `role:admin,super-admin`: el middleware debe
        // aceptar CUALQUIERA de los slugs listados.
        $superAdminRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'super-admin'],
            values: ['name' => 'Súper Administrador', 'is_active' => true],
        );

        $super = User::factory()->create(attributes: [
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);
        Sanctum::actingAs(user: $super);

        // super-admin pasa la regla `role:admin,super-admin` aunque
        // NO pasaría una regla `role:admin` estricta (cubierto abajo).
        $response = $this->getJson(uri: '/api/v1/__test_role_multi__');
        $response->assertOk();
        $response->assertJsonPath('ok', true);
    }

    #[Test]
    public function super_admin_slug_does_not_pass_strict_role_admin(): void
    {
        // El plan actual monta `role:admin` (estricto) en el panel
        // admin. super-admin NO debe poder entrar por esa ruta — esto
        // fija el comportamiento: si en el futuro queremos abrir el
        // panel a super-admin, hay que editar explícitamente el
        // routes/api.php para añadir el slug.
        $superAdminRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'super-admin'],
            values: ['name' => 'Súper Administrador', 'is_active' => true],
        );

        $super = User::factory()->create(attributes: [
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);
        Sanctum::actingAs(user: $super);

        $response = $this->getJson(uri: '/api/v1/__test_role_gate__');
        $response->assertStatus(403);
    }
}
