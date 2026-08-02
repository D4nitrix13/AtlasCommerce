<?php

declare(strict_types=1);

namespace Tests\Feature\Stores;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\Stores\StoreTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del admin theme endpoint.
 *
 * F3.6 del roadmap multi-instance. Verifica que el admin puede ver
 * y editar el theme de su store via API autenticada.
 */
#[Group('stores')]
#[Group('theme')]
#[Group('admin')]
final class AdminThemeApiTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $store;

    private User $admin;

    /**
     * Setup: crear tienda, dominio, y admin user autenticado.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::factory()->primary()->create();

        $this->store = Store::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create(['slug' => 'panda-estampados', 'name' => 'Panda Estampados']);

        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => 'pandas-estampados.com',
            'type' => 'domain',
            'is_active' => true,
        ]);

        $role = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );

        $this->admin = User::factory()->create([
            'email' => 'admin@panda-estampados.test',
            'is_active' => true,
            'role_id' => $role->id,
        ]);
        $this->admin->stores()->attach($this->store->id);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);
    }

    #[Test]
    public function get_theme_returns_defaults_when_not_configured(): void
    {
        $response = $this->getJson(uri: 'http://pandas-estampados.com/api/admin/theme');

        $response->assertOk();
        $response->assertJsonPath(path: 'data.short_name', expect: null);
        $response->assertJsonPath(path: 'data.accent_color', expect: null);
        $response->assertJsonPath(path: 'data.tokens', expect: []);
    }

    #[Test]
    public function get_theme_returns_existing_theme(): void
    {
        StoreTheme::query()->create([
            'store_id' => $this->store->id,
            'short_name' => 'Panda',
            'logo_path' => '/images/panda/logo.webp',
            'accent_color' => '#DC2626',
        ]);

        $response = $this->getJson(uri: 'http://pandas-estampados.com/api/admin/theme');

        $response->assertOk();
        $response->assertJsonPath(path: 'data.short_name', expect: 'Panda');
        $response->assertJsonPath(path: 'data.accent_color', expect: '#DC2626');
        $response->assertJsonPath(path: 'data.logo_path', expect: '/images/panda/logo.webp');
    }

    #[Test]
    public function patch_theme_creates_new_theme(): void
    {
        $response = $this->patchJson(uri: 'http://pandas-estampados.com/api/admin/theme', data: [
            'short_name' => 'Panda',
            'logo_path' => '/images/panda/logo.webp',
            'accent_color' => '#DC2626',
            'accent_hover_color' => '#B91C1C',
            'support_email' => 'support@panda-estampados.test',
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.short_name', expect: 'Panda');
        $response->assertJsonPath(path: 'data.accent_color', expect: '#DC2626');
        $response->assertJsonPath(path: 'data.support_email', expect: 'support@panda-estampados.test');

        $this->assertDatabaseHas('store_themes', [
            'store_id' => $this->store->id,
            'short_name' => 'Panda',
            'accent_color' => '#DC2626',
        ]);
    }

    #[Test]
    public function patch_theme_updates_existing_theme(): void
    {
        StoreTheme::query()->create([
            'store_id' => $this->store->id,
            'short_name' => 'Old Name',
            'accent_color' => '#000000',
        ]);

        $response = $this->patchJson(uri: 'http://pandas-estampados.com/api/admin/theme', data: [
            'short_name' => 'New Name',
            'accent_color' => '#FFFFFF',
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.short_name', expect: 'New Name');
        $response->assertJsonPath(path: 'data.accent_color', expect: '#FFFFFF');

        $this->assertDatabaseHas('store_themes', [
            'store_id' => $this->store->id,
            'short_name' => 'New Name',
            'accent_color' => '#FFFFFF',
        ]);
    }

    #[Test]
    public function patch_theme_validates_color_format(): void
    {
        $response = $this->patchJson(uri: 'http://pandas-estampados.com/api/admin/theme', data: [
            'accent_color' => 'not-a-hex',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['accent_color']);
    }

    #[Test]
    public function patch_theme_validates_email_format(): void
    {
        $response = $this->patchJson(uri: 'http://pandas-estampados.com/api/admin/theme', data: [
            'support_email' => 'not-an-email',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['support_email']);
    }

    #[Test]
    public function patch_theme_allows_partial_updates(): void
    {
        StoreTheme::query()->create([
            'store_id' => $this->store->id,
            'short_name' => 'Panda',
            'logo_path' => '/images/old-logo.webp',
            'accent_color' => '#DC2626',
        ]);

        // Solo actualizamos el color, el resto queda igual.
        $response = $this->patchJson(uri: 'http://pandas-estampados.com/api/admin/theme', data: [
            'accent_color' => '#FF00FF',
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.short_name', expect: 'Panda');
        $response->assertJsonPath(path: 'data.logo_path', expect: '/images/old-logo.webp');
        $response->assertJsonPath(path: 'data.accent_color', expect: '#FF00FF');
    }

    #[Test]
    public function get_theme_with_unknown_host_returns_404(): void
    {
        // Host que no matchea ningun store.
        $response = $this->getJson(uri: 'http://unknown-domain.com/api/admin/theme');

        $response->assertStatus(status: 404);
    }
}
