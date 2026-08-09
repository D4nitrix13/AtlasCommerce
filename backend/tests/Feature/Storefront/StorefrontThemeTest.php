<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\Stores\StoreTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del endpoint público `GET /api/storefront/theme`.
 *
 * Verifica que:
 *   - Devuelve el theme configurado para el store actual.
 *   - Devuelve defaults vacíos si el store no tiene theme.
 *   - Resuelve el store por host via `store.domain` middleware.
 */
#[Group('storefront')]
#[Group('theme')]
final class StorefrontThemeTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $store;

    /**
     * Crear store con dominio para que store.domain middleware
     * pueda resolverlo.
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
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function returns_theme_when_configured(): void
    {
        StoreTheme::query()->create([
            'store_id' => $this->store->id,
            'short_name' => 'Panda',
            'logo_path' => '/images/panda/logo.webp',
            'accent_color' => '#DC2626',
            'support_email' => 'support@panda-estampados.test',
        ]);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/storefront/theme');

        $response->assertOk();
        $response->assertJsonPath(path: 'data.short_name', expect: 'Panda');
        $response->assertJsonPath(path: 'data.logo_path', expect: '/images/panda/logo.webp');
        $response->assertJsonPath(path: 'data.accent_color', expect: '#DC2626');
        $response->assertJsonPath(path: 'data.support_email', expect: 'support@panda-estampados.test');
    }

    #[Test]
    public function returns_defaults_when_store_has_no_theme(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/storefront/theme');

        $response->assertOk();
        $response->assertJsonPath(path: 'data.short_name', expect: null);
        $response->assertJsonPath(path: 'data.logo_path', expect: null);
        $response->assertJsonPath(path: 'data.accent_color', expect: null);
        $response->assertJsonPath(path: 'data.tokens', expect: []);
    }

    #[Test]
    public function returns_tokens_json_array(): void
    {
        StoreTheme::query()->create([
            'store_id' => $this->store->id,
            'tokens' => [
                'font_family' => 'Inter',
                'font_size_base' => '16px',
                'border_radius' => '8px',
            ],
        ]);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/storefront/theme');

        $response->assertOk();
        $response->assertJsonPath(path: 'data.tokens.font_family', expect: 'Inter');
        $response->assertJsonPath(path: 'data.tokens.font_size_base', expect: '16px');
        $response->assertJsonPath(path: 'data.tokens.border_radius', expect: '8px');
    }

    #[Test]
    public function returns_404_when_host_does_not_match_a_store(): void
    {
        $response = $this->getJson(uri: 'http://unknown-domain.com/api/storefront/theme');

        $response->assertStatus(status: 404);
    }

    #[Test]
    public function each_store_has_independent_theme(): void
    {
        $otherStore = Store::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create(['slug' => 'kitsune', 'name' => 'Kitsune Store']);

        StoreDomain::query()->create([
            'store_id' => $otherStore->id,
            'domain' => 'kitsune.com',
            'is_active' => true,
        ]);

        StoreTheme::query()->create([
            'store_id' => $this->store->id,
            'short_name' => 'Panda',
            'accent_color' => '#DC2626',
        ]);
        StoreTheme::query()->create([
            'store_id' => $otherStore->id,
            'short_name' => 'Kitsune',
            'accent_color' => '#7C3AED',
        ]);

        $panda = $this->getJson(uri: 'http://panda-estampados.com/api/storefront/theme');
        $panda->assertJsonPath(path: 'data.short_name', expect: 'Panda');
        $panda->assertJsonPath(path: 'data.accent_color', expect: '#DC2626');

        $kitsune = $this->getJson(uri: 'http://kitsune.com/api/storefront/theme');
        $kitsune->assertJsonPath(path: 'data.short_name', expect: 'Kitsune');
        $kitsune->assertJsonPath(path: 'data.accent_color', expect: '#7C3AED');
    }

    #[Test]
    public function deleting_store_cascades_to_theme(): void
    {
        StoreTheme::query()->create([
            'store_id' => $this->store->id,
            'short_name' => 'Panda',
        ]);

        $this->assertDatabaseHas(table: 'store_themes', data: ['store_id' => $this->store->id]);

        // Forzar cascade borrando primero los dominios (FK restrict).
        StoreDomain::query()->where('store_id', $this->store->id)->delete();
        $this->store->forceDelete();

        $this->assertDatabaseMissing(table: 'store_themes', data: ['store_id' => $this->store->id]);
    }
}
