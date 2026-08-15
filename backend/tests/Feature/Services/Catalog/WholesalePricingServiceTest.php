<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\WholesalePrice;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreFeature;
use App\Services\Catalog\WholesalePricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del servicio de precios mayoristas.
 *
 * Verifica la lógica de la feature `wholesale_pricing`:
 * - Si el store NO tiene la feature, retorna precio base.
 * - Si tiene la feature, busca el precio mayorista aplicable según
 *   la cantidad (`min_qty` mas alto <= qty).
 * - Si no encuentra precio mayorista, retorna precio base.
 */
#[Group('services')]
#[Group('catalog')]
#[Group('wholesale')]
final class WholesalePricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private WholesalePricingService $service;

    private Currency $currency;

    private Store $store;

    private Product $product;

    /**
     * Setup: crear store y producto base. Service siempre listo.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(WholesalePricingService::class);

        $this->currency = Currency::factory()->primary()->create();

        $this->store = Store::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create(attributes: ['slug' => 'test-store', 'name' => 'Test Store']);

        $this->product = Product::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create(attributes: [
                'name' => 'Test Product',
                'base_price_cents' => 1000,
            ]);
    }

    #[Test]
    public function returns_base_price_when_store_has_no_wholesale_feature(): void
    {
        // Store sin la feature activada.
        $this->assertFalse($this->store->hasFeature('wholesale_pricing'));

        $price = $this->service->calculatePrice(
            product: $this->product,
            store: $this->store,
            quantity: 100,
        );

        $this->assertSame(expected: 1000, actual: $price);
    }

    #[Test]
    public function returns_base_price_when_feature_active_but_no_wholesale_prices_defined(): void
    {
        // Activar la feature pero no definir precios mayoristas.
        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'wholesale_pricing',
            'is_active' => true,
        ]);

        $price = $this->service->calculatePrice(
            product: $this->product,
            store: $this->store,
            quantity: 100,
        );

        // Sin precios definidos, fallback al base.
        $this->assertSame(expected: 1000, actual: $price);
    }

    #[Test]
    public function returns_wholesale_price_when_quantity_meets_min_qty(): void
    {
        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'wholesale_pricing',
            'is_active' => true,
        ]);
        WholesalePrice::query()->create(attributes: [
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'min_qty' => 10,
            'price_cents' => 800, // 20% descuento
        ]);

        $price = $this->service->calculatePrice(
            product: $this->product,
            store: $this->store,
            quantity: 10,
        );

        $this->assertSame(expected: 800, actual: $price);
    }

    #[Test]
    public function returns_base_price_when_quantity_below_min_qty(): void
    {
        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'wholesale_pricing',
            'is_active' => true,
        ]);
        WholesalePrice::query()->create(attributes: [
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'min_qty' => 10,
            'price_cents' => 800,
        ]);

        // Comprando 5 (menos que min_qty=10) → precio base.
        $price = $this->service->calculatePrice(
            product: $this->product,
            store: $this->store,
            quantity: 5,
        );

        $this->assertSame(expected: 1000, actual: $price);
    }

    #[Test]
    public function returns_highest_applicable_min_qty_price(): void
    {
        // Tier 1: 10+ unidades = 800
        // Tier 2: 50+ unidades = 600
        // Tier 3: 100+ unidades = 500
        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'wholesale_pricing',
            'is_active' => true,
        ]);
        WholesalePrice::query()->create(attributes: [
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'min_qty' => 10,
            'price_cents' => 800,
        ]);
        WholesalePrice::query()->create(attributes: [
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'min_qty' => 50,
            'price_cents' => 600,
        ]);
        WholesalePrice::query()->create(attributes: [
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'min_qty' => 100,
            'price_cents' => 500,
        ]);

        // Comprando 75 unidades → tier 50 (600).
        $this->assertSame(
            expected: 600,
            actual: $this->service->calculatePrice($this->product, $this->store, 75),
        );

        // Comprando 150 unidades → tier 100 (500).
        $this->assertSame(
            expected: 500,
            actual: $this->service->calculatePrice($this->product, $this->store, 150),
        );

        // Comprando 10 unidades → tier 10 (800).
        $this->assertSame(
            expected: 800,
            actual: $this->service->calculatePrice($this->product, $this->store, 10),
        );

        // Comprando 5 unidades → base (no llega al min de 10).
        $this->assertSame(
            expected: 1000,
            actual: $this->service->calculatePrice($this->product, $this->store, 5),
        );
    }

    #[Test]
    public function wholesale_prices_are_isolated_per_store(): void
    {
        $otherStore = Store::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create(attributes: ['slug' => 'other-store', 'name' => 'Other Store']);

        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'wholesale_pricing',
            'is_active' => true,
        ]);
        // Wholesale price SOLO para $this->store.
        WholesalePrice::query()->create(attributes: [
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'min_qty' => 10,
            'price_cents' => 500,
        ]);

        $this->assertSame(
            expected: 500,
            actual: $this->service->calculatePrice($this->product, $this->store, 20),
        );
        // Other store sin feature, retorna base.
        $this->assertSame(
            expected: 1000,
            actual: $this->service->calculatePrice($this->product, $otherStore, 20),
        );
    }

    #[Test]
    public function quantity_zero_or_negative_returns_base_price(): void
    {
        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'wholesale_pricing',
            'is_active' => true,
        ]);
        WholesalePrice::query()->create(attributes: [
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'min_qty' => 1,
            'price_cents' => 500,
        ]);

        // Quantity 0 → no tiene sentido, fallback a base.
        $this->assertSame(
            expected: 1000,
            actual: $this->service->calculatePrice($this->product, $this->store, 0),
        );

        // Quantity negativo → fallback.
        $this->assertSame(
            expected: 1000,
            actual: $this->service->calculatePrice($this->product, $this->store, -1),
        );
    }

    #[Test]
    public function inactive_feature_acts_as_if_not_present(): void
    {
        // Feature existe pero está inactiva.
        StoreFeature::query()->create(attributes: [
            'store_id' => $this->store->id,
            'slug' => 'wholesale_pricing',
            'is_active' => false,
        ]);
        WholesalePrice::query()->create(attributes: [
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'min_qty' => 10,
            'price_cents' => 500,
        ]);

        // Feature inactiva → no consulta wholesale_prices.
        $this->assertSame(
            expected: 1000,
            actual: $this->service->calculatePrice($this->product, $this->store, 100),
        );
    }
}
