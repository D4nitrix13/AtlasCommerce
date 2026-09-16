<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Marketing\Promotion;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del endpoint público `GET /api/v1/storefront/promotions` (RF-MKT-061).
 *
 * Cubre:
 *  - Happy: devuelve promos vigentes del tenant.
 *  - Aislamiento: solo promos globales o de la tienda activa.
 *  - Excluye inactivas, expiradas o no iniciadas.
 *  - Shape del resource (sin campos admin).
 *  - Resuelve tienda por host via middleware `store.domain`.
 */
#[Group('storefront')]
#[Group('promotion')]
final class StorefrontPromotionIndexTest extends TestCase
{
    use RefreshDatabase;

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
    public function it_returns_active_promotions_for_current_tenant(): void
    {
        Promotion::factory()->percentage(15.0)->create([
            'store_id' => null, // global
            'is_active' => true,
        ]);
        Promotion::factory()->percentage(20.0)->forStore($this->store)->create([
            'is_active' => true,
        ]);

        $response = $this->getJson(uri: $this->url());

        $response->assertOk();
        $response->assertJsonCount(2, key: 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'description', 'discount_type', 'value', 'starts_at', 'ends_at'],
            ],
        ]);
    }

    #[Test]
    public function it_excludes_promotions_from_other_tenants(): void
    {
        Promotion::factory()->percentage(15.0)->create([
            'store_id' => null,
            'is_active' => true,
        ]);
        Promotion::factory()->percentage(20.0)->forStore($this->otherStore)->create([
            'is_active' => true,
        ]);

        $response = $this->getJson(uri: $this->url());

        $response->assertOk();
        $response->assertJsonCount(1, key: 'data');
    }

    #[Test]
    public function it_excludes_inactive_and_expired_promotions(): void
    {
        // Activa válida — debe aparecer.
        Promotion::factory()->percentage(10.0)->create([
            'store_id' => null,
            'is_active' => true,
        ]);
        // Inactiva — NO debe aparecer.
        Promotion::factory()->percentage(20.0)->create([
            'store_id' => null,
            'is_active' => false,
        ]);
        // Expirada — NO debe aparecer.
        Promotion::factory()->percentage(30.0)->create([
            'store_id' => null,
            'is_active' => true,
            'starts_at' => now()->subDays(30)->toDateString(),
            'ends_at' => now()->subDays(1)->toDateString(),
        ]);
        // No iniciada — NO debe aparecer.
        Promotion::factory()->percentage(40.0)->create([
            'store_id' => null,
            'is_active' => true,
            'starts_at' => now()->addDays(1)->toDateString(),
            'ends_at' => now()->addDays(30)->toDateString(),
        ]);

        $response = $this->getJson(uri: $this->url());

        $response->assertOk();
        $response->assertJsonCount(1, key: 'data');
    }

    #[Test]
    public function it_does_not_leak_admin_fields(): void
    {
        Promotion::factory()->percentage(15.0)->create([
            'store_id' => null,
            'is_active' => true,
        ]);

        $response = $this->getJson(uri: $this->url());
        $response->assertOk();

        $first = $response->json(key: 'data.0');
        $this->assertArrayNotHasKey('store_id', $first);
        $this->assertArrayNotHasKey('is_active', $first);
    }

    #[Test]
    public function unknown_host_returns_404(): void
    {
        $response = $this->getJson(uri: 'http://unknown.test/api/v1/storefront/promotions');

        $response->assertNotFound();
    }

    /**
     * Helper: arma la URL con el dominio de la tienda en el path.
     */
    private function url(): string
    {
        return "http://{$this->host}/api/v1/storefront/promotions";
    }
}
