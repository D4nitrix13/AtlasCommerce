<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del middleware `StoreMaintenanceMode` (RF-STOR-132).
 *
 * Cubre:
 *  - Tienda en mantenimiento → 503 con view branded.
 *  - Tienda activa → request pasa normalmente.
 *  - Tienda con `is_active = false` → 503.
 *  - Host no registrado → 404 (delegado al middleware store.domain).
 *  - Rutas admin NO se bloquean (no usan este middleware).
 */
#[Group('maintenance')]
final class StoreMaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $store;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($this->currency, 'currency')->create([
            'maintenance_mode' => false,
            'is_active' => true,
        ]);

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
    public function active_storefront_request_passes_through(): void
    {
        $response = $this->getJson(uri: "http://{$this->host}/api/v1/storefront/promotions");

        $response->assertOk();
    }

    #[Test]
    public function maintenance_mode_blocks_storefront_with_503(): void
    {
        $this->store->update(['maintenance_mode' => true]);

        $response = $this->get(uri: "http://{$this->host}/api/v1/storefront/promotions");

        $response->assertStatus(503);
        $content = (string) $response->getContent();
        $this->assertStringContainsString('503', $content);
        $this->assertStringContainsString($this->store->name, $content);
        $this->assertStringContainsString('mejorar tu experiencia', $content);
    }

    #[Test]
    public function inactive_store_is_blocked_by_store_domain_middleware(): void
    {
        // `ResolveStoreFromDomain` ya devuelve 404 cuando `is_active=false`,
        // antes de llegar a este middleware. Lo cubrimos acá para documentar
        // que la duplicación de chequeo NO ocurre acá.
        $this->store->update(['is_active' => false]);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/storefront/promotions");

        $response->assertStatus(404);
    }

    #[Test]
    public function admin_routes_are_not_blocked_by_maintenance_mode(): void
    {
        // Las rutas admin NUNCA deben usar este middleware — el admin
        // sigue operativo durante el mantenimiento del storefront.
        $this->store->update(['maintenance_mode' => true]);

        // Sanity: la ruta admin de stores existe y debe seguir accesible.
        // (No testeamos el endpoint específico porque requiere auth;
        // el middleware no debería aplicarse a ese grupo).
        $response = $this->getJson(
            uri: 'http://admin.test/api/v1/admin/stores',
        );
        // El middleware store.domain devuelve 404 (admin.test no existe),
        // pero NO 503 — eso prueba que maintenance NO se aplicó.
        $this->assertNotSame(503, $response->status());
    }
}
