<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Http\Middleware\ResolveStoreFromDomain;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\Stores\StoreSocialLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del endpoint público `GET /api/v1/storefront/social-links`.
 *
 * Cubre:
 *  - Solo links activos (`is_active`) de la tienda resuelta por dominio.
 *  - Orden por `sort_order` asc, desempate por `id` asc.
 *  - Aislamiento multi-tenant: nunca expone links de otra tienda.
 *  - Lista vacía (200) si la tienda no tiene links.
 *  - Shape público: sin campos admin (`store_id`, `is_active`).
 *  - 503 cuando no hay tienda en contexto (defense-in-depth).
 */
#[Group('storefront')]
#[Group('social-links')]
final class StorefrontSocialLinkIndexTest extends TestCase
{
    use RefreshDatabase;

    private const string NO_STORE_MESSAGE = 'No se pueden mostrar las redes sociales: la tienda no está activa.';

    private Currency $currency;

    private Store $store;

    private Store $otherStore;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($this->currency, 'currency')->create();
        $this->otherStore = Store::factory()->for($this->currency, 'currency')->create();

        $this->host = "{$this->store->slug}.test";
        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => $this->host,
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_returns_active_links_for_current_tenant_ordered_by_sort_order(): void
    {
        $this->makeLink(platform: 'instagram', attributes: ['sort_order' => 2]);
        $this->makeLink(platform: 'facebook', attributes: [
            'sort_order' => 1,
            'display_name' => 'Panda FB',
            'icon_url' => 'https://cdn.test/fb.svg',
        ]);
        $this->makeLink(platform: 'tiktok', attributes: ['sort_order' => 3]);

        $response = $this->getJson(uri: $this->url());

        $response->assertOk();
        $response->assertJsonCount(3, key: 'data');
        $response->assertJsonPath(path: 'data.0.platform', expect: 'facebook');
        $response->assertJsonPath(path: 'data.0.sort_order', expect: 1);
        $response->assertJsonPath(path: 'data.0.display_name', expect: 'Panda FB');
        $response->assertJsonPath(path: 'data.0.icon_url', expect: 'https://cdn.test/fb.svg');
        $response->assertJsonPath(path: 'data.1.platform', expect: 'instagram');
        $response->assertJsonPath(path: 'data.2.platform', expect: 'tiktok');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['platform', 'url', 'display_name', 'icon_url', 'sort_order'],
            ],
        ]);
    }

    #[Test]
    public function it_excludes_inactive_links(): void
    {
        $this->makeLink(platform: 'instagram', attributes: ['sort_order' => 1]);
        $this->makeLink(platform: 'facebook', attributes: [
            'sort_order' => 2,
            'is_active' => false,
        ]);

        $response = $this->getJson(uri: $this->url());

        $response->assertOk();
        $response->assertJsonCount(1, key: 'data');
        $response->assertJsonPath(path: 'data.0.platform', expect: 'instagram');
    }

    #[Test]
    public function it_excludes_links_from_other_stores(): void
    {
        $this->makeLink(platform: 'instagram', attributes: ['sort_order' => 1]);
        StoreSocialLink::query()->create([
            'store_id' => $this->otherStore->id,
            'platform' => 'facebook',
            'url' => 'https://facebook.com/otra-tienda',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->getJson(uri: $this->url());

        $response->assertOk();
        $response->assertJsonCount(1, key: 'data');
        $response->assertJsonPath(path: 'data.0.platform', expect: 'instagram');
        $response->assertJsonPath(path: 'data.0.url', expect: 'https://instagram.com/panda-estampados');
    }

    #[Test]
    public function it_returns_empty_list_when_store_has_no_links(): void
    {
        $response = $this->getJson(uri: $this->url());

        $response->assertOk();
        $response->assertExactJson(['data' => []]);
    }

    #[Test]
    public function it_orders_by_sort_order_and_breaks_ties_by_id(): void
    {
        // Mismo `sort_order`: el desempate es por `id` asc (orden de creación).
        $this->makeLink(platform: 'instagram', attributes: ['sort_order' => 5]);
        $this->makeLink(platform: 'facebook', attributes: ['sort_order' => 5]);
        $this->makeLink(platform: 'tiktok', attributes: ['sort_order' => 5]);

        $response = $this->getJson(uri: $this->url());

        $response->assertOk();
        $response->assertJsonCount(3, key: 'data');
        $response->assertJsonPath(path: 'data.0.platform', expect: 'instagram');
        $response->assertJsonPath(path: 'data.1.platform', expect: 'facebook');
        $response->assertJsonPath(path: 'data.2.platform', expect: 'tiktok');
    }

    #[Test]
    public function it_returns_503_when_no_store_in_context(): void
    {
        // Defense-in-depth: el middleware `store.domain` corta antes con 404,
        // pero el controller debe responder 503 si el contexto queda vacío
        // (misma convención que `StorefrontAuthController`).
        $this->withoutMiddleware(ResolveStoreFromDomain::class);

        $response = $this->getJson(uri: $this->url());

        $response->assertStatus(status: 503);
        $response->assertJsonPath(path: 'message', expect: self::NO_STORE_MESSAGE);
    }

    #[Test]
    public function unknown_host_returns_404(): void
    {
        $response = $this->getJson(uri: 'http://unknown.test/api/v1/storefront/social-links');

        $response->assertStatus(status: 404);
        $response->assertJsonPath(path: 'message', expect: 'Store not found or inactive.');
    }

    /**
     * Helper: crea un link social de la tienda del test con defaults mínimos.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function makeLink(string $platform, array $attributes = []): StoreSocialLink
    {
        return StoreSocialLink::query()->create(array_merge([
            'store_id' => $this->store->id,
            'platform' => $platform,
            'url' => "https://{$platform}.com/panda-estampados",
            'is_active' => true,
            'sort_order' => 0,
        ], $attributes));
    }

    /**
     * Helper: arma la URL con el dominio de la tienda en el path.
     */
    private function url(): string
    {
        return "http://{$this->host}/api/v1/storefront/social-links";
    }
}
