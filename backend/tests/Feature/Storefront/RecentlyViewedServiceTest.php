<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Auth\User;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Settings\Currency;
use App\Models\Storefront\RecentlyViewedProduct;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Services\Storefront\RecentlyViewedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de `RecentlyViewedService` (productos vistos recientemente).
 *
 * Cubre:
 *  - dedupe por identidad + producto (la re-visita mueve al frente).
 *  - cap de `MAX_ENTRIES` con poda de los más viejos primero.
 *  - aislamiento entre identidad user y guest.
 *  - `excludeProductId` respetado en `list()`.
 *  - productos ocultos / inactivos filtrados del listado.
 *  - identidad ausente: `record()` lanza, `list()` devuelve vacío.
 */
#[Group('storefront')]
#[Group('recently-viewed')]
final class RecentlyViewedServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecentlyViewedService $service;

    private Store $store;

    private Category $category;

    private Brand $brand;

    private Currency $currency;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RecentlyViewedService;

        $this->currency = Currency::factory()->primary()->create();
        $this->category = Category::factory()->create(attributes: [
            'name' => 'Bebidas',
            'slug' => 'drinkware',
        ]);
        $this->brand = Brand::factory()->create(attributes: [
            'name' => 'Panda',
            'slug' => 'panda',
        ]);

        $this->store = Store::factory()
            ->for($this->currency, 'currency')
            ->create(['slug' => 'panda-estampados']);

        StoreDomain::query()->create(attributes: [
            'store_id' => $this->store->id,
            'domain' => 'panda-estampados.test',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeProduct(array $attributes = []): Product
    {
        $slug = $attributes['slug'] ?? 'taza-ceramica';
        $product = Product::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'name' => 'Taza ceramica',
            'slug' => $slug,
            'description' => 'Taza de ceramica para estampado.',
            'base_price_cents' => 1200,
            'is_active' => true,
            'has_condition' => false,
            'has_sizes' => false,
            'is_exclusive' => false,
        ], $attributes));

        ProductStore::query()->create(attributes: [
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'is_visible' => true,
        ]);

        return $product;
    }

    #[Test]
    public function it_dedupes_repeated_views_of_the_same_product_for_an_identity(): void
    {
        $product = $this->makeProduct();

        $this->service->record(userId: $this->user->id, guestToken: null, productId: $product->id);
        $this->service->record(userId: $this->user->id, guestToken: null, productId: $product->id);

        $this->assertSame(
            expected: 1,
            actual: RecentlyViewedProduct::query()->where('user_id', $this->user->id)->count(),
        );
    }

    #[Test]
    public function it_moves_the_revisited_product_to_the_front_of_the_list(): void
    {
        $first = $this->makeProduct(['slug' => 'taza-ceramica']);
        $second = $this->makeProduct(['slug' => 'libreta']);

        $base = now();
        $this->travelTo($base->copy()->subMinutes(10));
        $this->service->record(userId: $this->user->id, guestToken: null, productId: $first->id);

        $this->travelTo($base->copy()->subMinutes(5));
        $this->service->record(userId: $this->user->id, guestToken: null, productId: $second->id);

        // Re-visita del primero: debe pisar `viewed_at` y volver al frente.
        $this->travelTo($base);
        $this->service->record(userId: $this->user->id, guestToken: null, productId: $first->id);

        $listed = $this->service->list(
            userId: $this->user->id,
            guestToken: null,
            storeId: $this->store->id,
        );

        $this->assertSame(
            expected: [$first->id, $second->id],
            actual: $listed->pluck('id')->all(),
        );
    }

    #[Test]
    public function it_prunes_the_oldest_entries_when_the_cap_is_reached(): void
    {
        $products = collect(range(1, RecentlyViewedService::MAX_ENTRIES + 1))
            ->map(fn (int $i): Product => $this->makeProduct(['slug' => 'producto-'.$i]));

        $base = now();
        foreach ($products as $index => $product) {
            $this->travelTo($base->copy()->addMinutes($index));
            $this->service->record(userId: $this->user->id, guestToken: null, productId: $product->id);
        }

        $this->assertSame(
            expected: RecentlyViewedService::MAX_ENTRIES,
            actual: RecentlyViewedProduct::query()->where('user_id', $this->user->id)->count(),
        );

        // La más vieja (primera registrada) es la que sale.
        $this->assertDatabaseMissing(table: 'recently_viewed_products', data: [
            'user_id' => $this->user->id,
            'product_id' => $products->first()->id,
        ]);
    }

    #[Test]
    public function it_keeps_user_and_guest_identities_isolated(): void
    {
        $userProduct = $this->makeProduct(['slug' => 'taza-ceramica']);
        $guestProduct = $this->makeProduct(['slug' => 'libreta']);

        $this->service->record(userId: $this->user->id, guestToken: null, productId: $userProduct->id);
        $this->service->record(userId: null, guestToken: 'guest-token-1', productId: $guestProduct->id);

        $userList = $this->service->list(
            userId: $this->user->id,
            guestToken: null,
            storeId: $this->store->id,
        );
        $guestList = $this->service->list(
            userId: null,
            guestToken: 'guest-token-1',
            storeId: $this->store->id,
        );

        $this->assertSame(
            expected: [$userProduct->id],
            actual: $userList->pluck('id')->all(),
        );
        $this->assertSame(
            expected: [$guestProduct->id],
            actual: $guestList->pluck('id')->all(),
        );
    }

    #[Test]
    public function it_excludes_the_requested_product_from_the_list(): void
    {
        $first = $this->makeProduct(['slug' => 'taza-ceramica']);
        $second = $this->makeProduct(['slug' => 'libreta']);

        $this->service->record(userId: $this->user->id, guestToken: null, productId: $first->id);
        $this->service->record(userId: $this->user->id, guestToken: null, productId: $second->id);

        $listed = $this->service->list(
            userId: $this->user->id,
            guestToken: null,
            storeId: $this->store->id,
            excludeProductId: $first->id,
        );

        $this->assertSame(
            expected: [$second->id],
            actual: $listed->pluck('id')->all(),
        );
    }

    #[Test]
    public function it_filters_out_hidden_and_inactive_products_from_the_list(): void
    {
        $visible = $this->makeProduct(['slug' => 'taza-ceramica']);
        $hidden = $this->makeProduct(['slug' => 'libreta']);
        $inactive = $this->makeProduct(['slug' => 'tote-bag', 'is_active' => false]);

        ProductStore::query()
            ->where('product_id', $hidden->id)
            ->update(['is_visible' => false]);

        $this->service->record(userId: $this->user->id, guestToken: null, productId: $visible->id);
        $this->service->record(userId: $this->user->id, guestToken: null, productId: $hidden->id);
        $this->service->record(userId: $this->user->id, guestToken: null, productId: $inactive->id);

        $listed = $this->service->list(
            userId: $this->user->id,
            guestToken: null,
            storeId: $this->store->id,
        );

        $this->assertSame(
            expected: [$visible->id],
            actual: $listed->pluck('id')->all(),
        );
    }

    #[Test]
    public function it_throws_when_neither_user_nor_guest_identity_is_present(): void
    {
        $product = $this->makeProduct();

        $this->expectException(InvalidArgumentException::class);

        $this->service->record(userId: null, guestToken: null, productId: $product->id);
    }

    #[Test]
    public function it_returns_an_empty_list_when_no_identity_is_present(): void
    {
        $listed = $this->service->list(
            userId: null,
            guestToken: null,
            storeId: $this->store->id,
        );

        $this->assertCount(0, $listed);
    }
}
