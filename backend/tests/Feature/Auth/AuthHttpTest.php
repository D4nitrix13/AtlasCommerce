<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('secret-password'),
            'is_active' => true,
            'role_id' => $this->adminRole->id,
        ]);
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
                'is_active',
                'permissions',
            ],
        ]);

        $this->assertNotEmpty($response->json('token'));
        $this->assertSame('test@example.com', $response->json('user.email'));
        $this->assertSame('admin', $response->json('user.role.slug'));
        $this->assertTrue($response->json('user.is_active'));
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
            'password' => Hash::make('secret-password'),
            'is_active' => true,
            'role_id' => $staffRole->id,
        ]);

        Sanctum::actingAs($staffUser, abilities: ['*']);

        $response = $this->getJson('/api/auth/me');

        $response->assertOk();
        $response->assertJsonPath('user.role.slug', 'staff');
        $response->assertJsonPath('user.permissions', ['shipping.shipments.ship']);
    }
}