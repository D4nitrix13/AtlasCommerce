<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Marketing;

use App\Models\Catalog\Product;
use App\Models\Marketing\Promotion;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Services\Marketing\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests unitarios del `PromotionService` (RF-MKT-061).
 *
 * Cubre:
 *  - Vigencia: is_active, fechas, tenant.
 *  - Cálculo: percentage, fixed, tipos no soportados.
 *  - Combinaciones (cupón roto NO bloquea — `isApplicable` retorna false).
 */
#[Group('marketing')]
final class PromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PromotionService $service;

    private Store $store;

    private Currency $currency;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PromotionService::class);

        $this->currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($this->currency, 'currency')->create();
        $this->product = Product::factory()->for($this->currency, 'currency')->create([
            'base_price_cents' => 10_00,
        ]);
    }

    #[TestAttr]
    public function inactive_promotion_is_not_applicable(): void
    {
        $promotion = Promotion::factory()->percentage(20.0)->create([
            'store_id' => $this->store->id,
            'is_active' => false,
        ]);

        $this->assertFalse($this->service->isApplicable(promotion: $promotion, store: $this->store));
        $this->assertSame(0, $this->service->calculateDiscountCents(
            promotion: $promotion,
            product: $this->product,
            store: $this->store,
        ));
    }

    #[TestAttr]
    public function promotion_from_other_store_is_not_applicable(): void
    {
        $otherStore = Store::factory()->for($this->currency, 'currency')->create();
        $promotion = Promotion::factory()->forStore($otherStore)->create();

        $this->assertFalse($this->service->isApplicable(promotion: $promotion, store: $this->store));
    }

    #[TestAttr]
    public function not_yet_started_promotion_is_not_applicable(): void
    {
        $promotion = Promotion::factory()->percentage(20.0)->create([
            'store_id' => $this->store->id,
            'starts_at' => now()->addDays(7)->toDateString(),
            'ends_at' => now()->addDays(30)->toDateString(),
        ]);

        $this->assertFalse($this->service->isApplicable(promotion: $promotion, store: $this->store));
    }

    #[TestAttr]
    public function expired_promotion_is_not_applicable(): void
    {
        $promotion = Promotion::factory()->percentage(20.0)->create([
            'store_id' => $this->store->id,
            'starts_at' => now()->subDays(30)->toDateString(),
            'ends_at' => now()->subDays(1)->toDateString(),
        ]);

        $this->assertFalse($this->service->isApplicable(promotion: $promotion, store: $this->store));
    }

    #[TestAttr]
    public function global_promotion_applies_to_any_store(): void
    {
        $promotion = Promotion::factory()->percentage(20.0)->create(['store_id' => null]);

        $this->assertTrue($this->service->isApplicable(promotion: $promotion, store: $this->store));
    }

    #[TestAttr]
    public function percentage_discount_calculates_from_product_price(): void
    {
        $promotion = Promotion::factory()->percentage(20.0)->create(['store_id' => $this->store->id]);

        // 1000 cents * 20% = 200 cents.
        $this->assertSame(200, $this->service->calculateDiscountCents(
            promotion: $promotion,
            product: $this->product,
            store: $this->store,
        ));
    }

    #[TestAttr]
    public function fixed_discount_calculates_exact_cents(): void
    {
        $promotion = Promotion::factory()->fixed(cents: 5_00)->create(['store_id' => $this->store->id]);

        $this->assertSame(500, $this->service->calculateDiscountCents(
            promotion: $promotion,
            product: $this->product,
            store: $this->store,
        ));
    }

    #[TestAttr]
    public function unsupported_discount_type_returns_zero(): void
    {
        $promotion = Promotion::factory()->create([
            'store_id' => $this->store->id,
            'discount_type' => 'buy_x_get_y', // MVP no soporta
            'value' => 0,
        ]);

        $this->assertSame(0, $this->service->calculateDiscountCents(
            promotion: $promotion,
            product: $this->product,
            store: $this->store,
        ));
    }
}
