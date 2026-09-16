<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Marketing;

use App\Models\Carts\Cart;
use App\Models\Catalog\Product;
use App\Models\Marketing\Promotion;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Services\Marketing\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del cálculo de promociones a nivel carrito (RF-MKT-061).
 *
 * Cubre:
 *  - Carrito vacío → 0.
 *  - Sin promos aplicables → 0.
 *  - Porcentaje aplicado correctamente × cantidad.
 *  - Fijo aplicado correctamente × cantidad.
 *  - Mejor descuento (no acumulación) entre múltiples promos del mismo item.
 *  - Descuento acotado al subtotal del item (defensivo).
 *  - Excluye promos de otra tienda.
 *  - Excluye promos expiradas o inactivas.
 */
#[Group('marketing')]
#[Group('promotion')]
final class PromotionCartCalculationTest extends TestCase
{
    use RefreshDatabase;

    private PromotionService $service;

    private Currency $currency;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PromotionService::class);

        $this->currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($this->currency, 'currency')->create();
    }

    #[Test]
    public function empty_cart_returns_zero(): void
    {
        $cart = Cart::factory()->for($this->store)->for($this->currency)->create();

        $this->assertSame(0, $this->service->calculateForCart(cart: $cart, store: $this->store));
    }

    #[Test]
    public function cart_without_promotions_returns_zero(): void
    {
        $product = Product::factory()->for($this->currency, 'currency')->create([
            'base_price_cents' => 10_00,
        ]);
        $cart = $this->makeCartWithItem($product, quantity: 1, subtotalCents: 10_00);

        $this->assertSame(0, $this->service->calculateForCart(cart: $cart, store: $this->store));
    }

    #[Test]
    public function percentage_promo_applies_to_each_unit(): void
    {
        $product = Product::factory()->for($this->currency, 'currency')->create([
            'base_price_cents' => 10_00,
        ]);
        $cart = $this->makeCartWithItem($product, quantity: 3, subtotalCents: 30_00);

        Promotion::factory()
            ->percentage(10.0)
            ->forStore($this->store)
            ->withProduct($product)
            ->create();

        // 10% de 1000 = 100 por unidad × 3 unidades = 300 cents.
        $this->assertSame(300, $this->service->calculateForCart(cart: $cart, store: $this->store));
    }

    #[Test]
    public function fixed_promo_applies_to_each_unit(): void
    {
        $product = Product::factory()->for($this->currency, 'currency')->create([
            'base_price_cents' => 10_00,
        ]);
        $cart = $this->makeCartWithItem($product, quantity: 2, subtotalCents: 20_00);

        Promotion::factory()
            ->fixed(cents: 5_00)
            ->forStore($this->store)
            ->withProduct($product)
            ->create();

        // 500 cents fijos × 2 unidades = 1000.
        $this->assertSame(1_000, $this->service->calculateForCart(cart: $cart, store: $this->store));
    }

    #[Test]
    public function best_discount_wins_when_multiple_promos_apply(): void
    {
        $product = Product::factory()->for($this->currency, 'currency')->create([
            'base_price_cents' => 10_00,
        ]);
        $cart = $this->makeCartWithItem($product, quantity: 1, subtotalCents: 10_00);

        // Promo chica: 10% = 100.
        Promotion::factory()
            ->percentage(10.0)
            ->forStore($this->store)
            ->withProduct($product)
            ->create();

        // Promo grande: 25% = 250 (gana).
        Promotion::factory()
            ->percentage(25.0)
            ->forStore($this->store)
            ->withProduct($product)
            ->create();

        $this->assertSame(250, $this->service->calculateForCart(cart: $cart, store: $this->store));
    }

    #[Test]
    public function discount_is_capped_at_item_subtotal(): void
    {
        // Precio base bajo, promo fijo grande: el descuento por unidad
        // excede el subtotal → se acota al subtotal.
        $product = Product::factory()->for($this->currency, 'currency')->create([
            'base_price_cents' => 1_00,
        ]);
        $cart = $this->makeCartWithItem($product, quantity: 1, subtotalCents: 1_00);

        Promotion::factory()
            ->fixed(cents: 5_00)
            ->forStore($this->store)
            ->withProduct($product)
            ->create();

        // 500 excede 100 → se acota a 100.
        $this->assertSame(100, $this->service->calculateForCart(cart: $cart, store: $this->store));
    }

    #[Test]
    public function promo_from_other_store_is_ignored(): void
    {
        $otherStore = Store::factory()->for($this->currency, 'currency')->create();

        $product = Product::factory()->for($this->currency, 'currency')->create([
            'base_price_cents' => 10_00,
        ]);
        $cart = $this->makeCartWithItem($product, quantity: 1, subtotalCents: 10_00);

        Promotion::factory()
            ->percentage(50.0)
            ->forStore($otherStore)
            ->withProduct($product)
            ->create();

        $this->assertSame(0, $this->service->calculateForCart(cart: $cart, store: $this->store));
    }

    #[Test]
    public function inactive_promo_is_ignored(): void
    {
        $product = Product::factory()->for($this->currency, 'currency')->create([
            'base_price_cents' => 10_00,
        ]);
        $cart = $this->makeCartWithItem($product, quantity: 1, subtotalCents: 10_00);

        Promotion::factory()
            ->percentage(50.0)
            ->forStore($this->store)
            ->withProduct($product)
            ->create([
                'is_active' => false,
            ]);

        $this->assertSame(0, $this->service->calculateForCart(cart: $cart, store: $this->store));
    }

    #[Test]
    public function expired_promo_is_ignored(): void
    {
        $product = Product::factory()->for($this->currency, 'currency')->create([
            'base_price_cents' => 10_00,
        ]);
        $cart = $this->makeCartWithItem($product, quantity: 1, subtotalCents: 10_00);

        Promotion::factory()
            ->percentage(50.0)
            ->forStore($this->store)
            ->withProduct($product)
            ->create([
                'starts_at' => now()->subDays(30)->toDateString(),
                'ends_at' => now()->subDays(1)->toDateString(),
            ]);

        $this->assertSame(0, $this->service->calculateForCart(cart: $cart, store: $this->store));
    }

    #[Test]
    public function promo_accumulates_across_items(): void
    {
        $productA = Product::factory()->for($this->currency, 'currency')->create([
            'base_price_cents' => 10_00,
        ]);
        $productB = Product::factory()->for($this->currency, 'currency')->create([
            'base_price_cents' => 20_00,
        ]);

        $cart = Cart::factory()->for($this->store)->for($this->currency)->create();
        $cart->items()->create([
            'product_id' => $productA->id,
            'cart_line_key' => 'a',
            'quantity' => 1,
            'unit_price_cents' => 10_00,
            'subtotal_cents' => 10_00,
        ]);
        $cart->items()->create([
            'product_id' => $productB->id,
            'cart_line_key' => 'b',
            'quantity' => 1,
            'unit_price_cents' => 20_00,
            'subtotal_cents' => 20_00,
        ]);

        Promotion::factory()
            ->percentage(10.0)
            ->forStore($this->store)
            ->withProduct($productA)
            ->create();

        Promotion::factory()
            ->percentage(20.0)
            ->forStore($this->store)
            ->withProduct($productB)
            ->create();

        // 10% de 1000 = 100 + 20% de 2000 = 400 → total 500.
        $this->assertSame(500, $this->service->calculateForCart(cart: $cart, store: $this->store));
    }

    private function makeCartWithItem(Product $product, int $quantity, int $subtotalCents): Cart
    {
        $cart = Cart::factory()->for($this->store)->for($this->currency)->create();
        $cart->items()->create([
            'product_id' => $product->id,
            'cart_line_key' => 'k',
            'quantity' => $quantity,
            'unit_price_cents' => $subtotalCents,
            'subtotal_cents' => $subtotalCents,
        ]);

        return $cart;
    }
}
