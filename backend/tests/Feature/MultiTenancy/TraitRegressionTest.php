<?php

declare(strict_types=1);

namespace Tests\Feature\MultiTenancy;

use App\Models\Carts\Wishlist;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Marketing\Coupon;
use App\Models\Marketing\Promotion;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Support\ProductQuestion;
use App\Models\Support\ProductReview;
use App\Models\Support\SupportTicket;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cobertura de regresión del trait `BelongsToStore` post-widening (H4.a).
 *
 * Verifica el contrato ampliado del trait: cuando hay contexto de
 * tienda activo, una consulta devuelve filas donde `store_id = X`
 * **o** `store_id IS NULL` (filas "globales" del sistema visibles
 * desde cualquier contexto).
 *
 * Cobertura: 6 modelos con `store_id` nullable × 3 escenarios =
 * 18 tests:
 *  1. Contexto activo: ve filas propias + filas NULL (globales).
 *  2. Filas NULL visibles desde CUALQUIER contexto (A o B).
 *  3. `withoutStoreScope()` retorna todas las filas (escape hatch).
 *
 * Los 6 modelos cubiertos son representativos del conjunto de 8
 * modelos que adoptan el trait en la task 2.2:
 * Wishlist, Coupon, Promotion, ProductReview, ProductQuestion,
 * SupportTicket.
 *
 * ¿Por qué no los 6 modelos existentes (Order, Invoice, etc.)?
 * Esas tablas tienen `store_id` NOT NULL por migración — no admiten
 * filas `NULL`, por lo que el widening del trait es un no-op sobre
 * ellas y no puede validarse con fail-first. El widening es
 * observable en modelos con columnas nullable (los 8 nuevos).
 *
 * Antes del fix (trait strict):
 *   Query desde storeA con filas (storeA, storeB, NULL) → 1 fila.
 *
 * Después del fix (trait con `OR store_id IS NULL`):
 *   Query desde storeA con filas (storeA, storeB, NULL) → 2 filas
 *   (storeA + NULL).
 */
#[Group('multitenancy')]
final class TraitRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $storeA;

    private Store $storeB;

    private Customer $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::factory()->primary()->create();
        $this->storeA = Store::factory()
            ->for($this->currency, 'currency')
            ->create(attributes: ['name' => 'Tienda A']);
        $this->storeB = Store::factory()
            ->for($this->currency, 'currency')
            ->create(attributes: ['name' => 'Tienda B']);
        $this->customer = Customer::factory()->create();

        $category = Category::factory()->create();
        $this->product = Product::factory()
            ->for($category, 'category')
            ->for($this->currency, 'currency')
            ->create();
    }

    // =====================================================================
    // Wishlist
    // =====================================================================

    #[Test]
    public function it_wishlist_returns_only_records_owned_by_or_global_in_active_store(): void
    {
        $this->makeWishlist($this->storeA);
        $this->makeWishlist($this->storeB);
        $this->makeWishlist(null);

        app(StoreContextService::class)->setStore($this->storeA);

        $this->assertSame(expected: 2, actual: Wishlist::query()->count());
    }

    #[Test]
    public function it_wishlist_global_records_visible_from_any_store_context(): void
    {
        $this->makeWishlist($this->storeA);
        $global = $this->makeWishlist(null);
        $this->makeWishlist($this->storeB);

        app(StoreContextService::class)->setStore($this->storeA);
        $this->assertSame(expected: 2, actual: Wishlist::query()->count());
        $this->assertNotNull(Wishlist::query()->find($global->id));

        app(StoreContextService::class)->setStore($this->storeB);
        $this->assertSame(expected: 2, actual: Wishlist::query()->count());
        $this->assertNotNull(Wishlist::query()->find($global->id));
    }

    #[Test]
    public function it_wishlist_without_store_scope_returns_all_rows(): void
    {
        $this->makeWishlist($this->storeA);
        $this->makeWishlist($this->storeB);
        $this->makeWishlist(null);

        app(StoreContextService::class)->setStore($this->storeA);

        $this->assertSame(expected: 3, actual: Wishlist::query()->withoutStoreScope()->count());
    }

    // =====================================================================
    // Coupon
    // =====================================================================

    #[Test]
    public function it_coupon_returns_only_records_owned_by_or_global_in_active_store(): void
    {
        $this->makeCoupon($this->storeA);
        $this->makeCoupon($this->storeB);
        $this->makeCoupon(null);

        app(StoreContextService::class)->setStore($this->storeA);

        $this->assertSame(expected: 2, actual: Coupon::query()->count());
    }

    #[Test]
    public function it_coupon_global_records_visible_from_any_store_context(): void
    {
        $this->makeCoupon($this->storeA);
        $global = $this->makeCoupon(null);
        $this->makeCoupon($this->storeB);

        app(StoreContextService::class)->setStore($this->storeA);
        $this->assertSame(expected: 2, actual: Coupon::query()->count());
        $this->assertNotNull(Coupon::query()->find($global->id));

        app(StoreContextService::class)->setStore($this->storeB);
        $this->assertSame(expected: 2, actual: Coupon::query()->count());
        $this->assertNotNull(Coupon::query()->find($global->id));
    }

    #[Test]
    public function it_coupon_without_store_scope_returns_all_rows(): void
    {
        $this->makeCoupon($this->storeA);
        $this->makeCoupon($this->storeB);
        $this->makeCoupon(null);

        app(StoreContextService::class)->setStore($this->storeA);

        $this->assertSame(expected: 3, actual: Coupon::query()->withoutStoreScope()->count());
    }

    // =====================================================================
    // Promotion
    // =====================================================================

    #[Test]
    public function it_promotion_returns_only_records_owned_by_or_global_in_active_store(): void
    {
        $this->makePromotion($this->storeA);
        $this->makePromotion($this->storeB);
        $this->makePromotion(null);

        app(StoreContextService::class)->setStore($this->storeA);

        $this->assertSame(expected: 2, actual: Promotion::query()->count());
    }

    #[Test]
    public function it_promotion_global_records_visible_from_any_store_context(): void
    {
        $this->makePromotion($this->storeA);
        $global = $this->makePromotion(null);
        $this->makePromotion($this->storeB);

        app(StoreContextService::class)->setStore($this->storeA);
        $this->assertSame(expected: 2, actual: Promotion::query()->count());
        $this->assertNotNull(Promotion::query()->find($global->id));

        app(StoreContextService::class)->setStore($this->storeB);
        $this->assertSame(expected: 2, actual: Promotion::query()->count());
        $this->assertNotNull(Promotion::query()->find($global->id));
    }

    #[Test]
    public function it_promotion_without_store_scope_returns_all_rows(): void
    {
        $this->makePromotion($this->storeA);
        $this->makePromotion($this->storeB);
        $this->makePromotion(null);

        app(StoreContextService::class)->setStore($this->storeA);

        $this->assertSame(expected: 3, actual: Promotion::query()->withoutStoreScope()->count());
    }

    // =====================================================================
    // ProductReview
    // =====================================================================

    #[Test]
    public function it_product_review_returns_only_records_owned_by_or_global_in_active_store(): void
    {
        $this->makeProductReview($this->storeA);
        $this->makeProductReview($this->storeB);
        $this->makeProductReview(null);

        app(StoreContextService::class)->setStore($this->storeA);

        $this->assertSame(expected: 2, actual: ProductReview::query()->count());
    }

    #[Test]
    public function it_product_review_global_records_visible_from_any_store_context(): void
    {
        $this->makeProductReview($this->storeA);
        $global = $this->makeProductReview(null);
        $this->makeProductReview($this->storeB);

        app(StoreContextService::class)->setStore($this->storeA);
        $this->assertSame(expected: 2, actual: ProductReview::query()->count());
        $this->assertNotNull(ProductReview::query()->find($global->id));

        app(StoreContextService::class)->setStore($this->storeB);
        $this->assertSame(expected: 2, actual: ProductReview::query()->count());
        $this->assertNotNull(ProductReview::query()->find($global->id));
    }

    #[Test]
    public function it_product_review_without_store_scope_returns_all_rows(): void
    {
        $this->makeProductReview($this->storeA);
        $this->makeProductReview($this->storeB);
        $this->makeProductReview(null);

        app(StoreContextService::class)->setStore($this->storeA);

        $this->assertSame(expected: 3, actual: ProductReview::query()->withoutStoreScope()->count());
    }

    // =====================================================================
    // ProductQuestion
    // =====================================================================

    #[Test]
    public function it_product_question_returns_only_records_owned_by_or_global_in_active_store(): void
    {
        $this->makeProductQuestion($this->storeA);
        $this->makeProductQuestion($this->storeB);
        $this->makeProductQuestion(null);

        app(StoreContextService::class)->setStore($this->storeA);

        $this->assertSame(expected: 2, actual: ProductQuestion::query()->count());
    }

    #[Test]
    public function it_product_question_global_records_visible_from_any_store_context(): void
    {
        $this->makeProductQuestion($this->storeA);
        $global = $this->makeProductQuestion(null);
        $this->makeProductQuestion($this->storeB);

        app(StoreContextService::class)->setStore($this->storeA);
        $this->assertSame(expected: 2, actual: ProductQuestion::query()->count());
        $this->assertNotNull(ProductQuestion::query()->find($global->id));

        app(StoreContextService::class)->setStore($this->storeB);
        $this->assertSame(expected: 2, actual: ProductQuestion::query()->count());
        $this->assertNotNull(ProductQuestion::query()->find($global->id));
    }

    #[Test]
    public function it_product_question_without_store_scope_returns_all_rows(): void
    {
        $this->makeProductQuestion($this->storeA);
        $this->makeProductQuestion($this->storeB);
        $this->makeProductQuestion(null);

        app(StoreContextService::class)->setStore($this->storeA);

        $this->assertSame(expected: 3, actual: ProductQuestion::query()->withoutStoreScope()->count());
    }

    // =====================================================================
    // SupportTicket
    // =====================================================================

    #[Test]
    public function it_support_ticket_returns_only_records_owned_by_or_global_in_active_store(): void
    {
        $this->makeSupportTicket($this->storeA);
        $this->makeSupportTicket($this->storeB);
        $this->makeSupportTicket(null);

        app(StoreContextService::class)->setStore($this->storeA);

        $this->assertSame(expected: 2, actual: SupportTicket::query()->count());
    }

    #[Test]
    public function it_support_ticket_global_records_visible_from_any_store_context(): void
    {
        $this->makeSupportTicket($this->storeA);
        $global = $this->makeSupportTicket(null);
        $this->makeSupportTicket($this->storeB);

        app(StoreContextService::class)->setStore($this->storeA);
        $this->assertSame(expected: 2, actual: SupportTicket::query()->count());
        $this->assertNotNull(SupportTicket::query()->find($global->id));

        app(StoreContextService::class)->setStore($this->storeB);
        $this->assertSame(expected: 2, actual: SupportTicket::query()->count());
        $this->assertNotNull(SupportTicket::query()->find($global->id));
    }

    #[Test]
    public function it_support_ticket_without_store_scope_returns_all_rows(): void
    {
        $this->makeSupportTicket($this->storeA);
        $this->makeSupportTicket($this->storeB);
        $this->makeSupportTicket(null);

        app(StoreContextService::class)->setStore($this->storeA);

        $this->assertSame(expected: 3, actual: SupportTicket::query()->withoutStoreScope()->count());
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * Crea un Wishlist opcionalmente asociado a una tienda.
     *
     * @return Wishlist Modelo recién persistido.
     */
    private function makeWishlist(?Store $store): Wishlist
    {
        return Wishlist::create([
            'customer_id' => $this->customer->id,
            'store_id' => $store?->id,
            'name' => 'Wishlist '.uniqid(),
            'is_default' => false,
        ]);
    }

    /**
     * Crea un Coupon opcionalmente asociado a una tienda.
     *
     * @return Coupon Modelo recién persistido.
     */
    private function makeCoupon(?Store $store): Coupon
    {
        return Coupon::create([
            'store_id' => $store?->id,
            'code' => 'C-'.strtoupper(uniqid()),
            'discount_type' => 'percentage',
            'value' => 10.0,
            'minimum_amount' => 0,
            'starts_at' => now()->subDays(7)->toDateString(),
            'ends_at' => now()->addDays(30)->toDateString(),
            'usage_limit' => 100,
            'used_count' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * Crea un Promotion opcionalmente asociado a una tienda.
     *
     * @return Promotion Modelo recién persistido.
     */
    private function makePromotion(?Store $store): Promotion
    {
        return Promotion::create([
            'store_id' => $store?->id,
            'name' => 'Promo '.uniqid(),
            'description' => 'Test',
            'discount_type' => 'percentage',
            'value' => 15.0,
            'starts_at' => now()->subDays(7)->toDateString(),
            'ends_at' => now()->addDays(30)->toDateString(),
            'is_active' => true,
        ]);
    }

    /**
     * Crea un ProductReview opcionalmente asociado a una tienda.
     *
     * @return ProductReview Modelo recién persistido.
     */
    private function makeProductReview(?Store $store): ProductReview
    {
        return ProductReview::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'store_id' => $store?->id,
            'rating' => 5,
            'comment' => 'Test review',
            'is_approved' => true,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Crea un ProductQuestion opcionalmente asociado a una tienda.
     *
     * @return ProductQuestion Modelo recién persistido.
     */
    private function makeProductQuestion(?Store $store): ProductQuestion
    {
        return ProductQuestion::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'store_id' => $store?->id,
            'question' => 'Test question '.uniqid(),
            'asked_at' => now(),
        ]);
    }

    /**
     * Crea un SupportTicket opcionalmente asociado a una tienda.
     *
     * @return SupportTicket Modelo recién persistido.
     */
    private function makeSupportTicket(?Store $store): SupportTicket
    {
        return SupportTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $store?->id,
            'subject' => 'Subject '.uniqid(),
            'description' => 'Test description',
            'status' => 'open',
            'priority' => 'normal',
        ]);
    }
}
