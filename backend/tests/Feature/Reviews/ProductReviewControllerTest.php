<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Models\Auth\User;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\Support\ProductReview;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests de reseñas de producto — endpoints cliente + público (RF-SUP-161).
 *
 * Cubre:
 *  - 401 sin token.
 *  - Cliente crea reseña (is_approved=false por default).
 *  - Una reseña por producto por cliente (duplicado → 409).
 *  - Validación rating 1-5, comment >= 10 chars.
 *  - Público: GET /products/{product}/reviews devuelve solo aprobadas + meta.
 *  - Cliente lista SUS reseñas (incluye pendientes).
 */
#[Group('reviews')]
final class ProductReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Product $product;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->for(
            Currency::factory()->primary()->create(),
            'currency',
        )->create(['slug' => 'panda']);
        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create(['user_id' => $this->user->id]);
        $this->customer->stores()->attach($this->store->id);

        $this->product = Product::factory()->for($this->store->currency, 'currency')->create();

        app(StoreContextService::class)->setStore($this->store);
    }

    #[TestAttr]
    public function store_requires_authentication(): void
    {
        $this->postJson(
            uri: "http://panda-estampados.com/api/v1/products/{$this->product->id}/reviews",
            data: ['rating' => 5, 'comment' => 'Excelente producto.'],
        )->assertStatus(status: 401);
    }

    #[TestAttr]
    public function it_creates_review(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/v1/products/{$this->product->id}/reviews",
            data: ['rating' => 5, 'comment' => 'Excelente producto.'],
        );

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.rating', expect: 5);
        $response->assertJsonPath(path: 'data.is_approved', expect: false);
    }

    #[TestAttr]
    public function duplicate_review_returns_409(): void
    {
        ProductReview::factory()->create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/v1/products/{$this->product->id}/reviews",
            data: ['rating' => 3, 'comment' => 'Cambié de opinión.'],
        );

        $response->assertStatus(status: 409);
    }

    #[TestAttr]
    public function it_validates_rating_range(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/v1/products/{$this->product->id}/reviews",
            data: ['rating' => 7],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['rating']);
    }

    #[TestAttr]
    public function it_validates_comment_min_length(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/v1/products/{$this->product->id}/reviews",
            data: ['rating' => 5, 'comment' => 'ok'],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['comment']);
    }

    #[TestAttr]
    public function public_index_returns_only_approved_with_average(): void
    {
        ProductReview::factory()->approved()->create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'rating' => 5,
        ]);
        ProductReview::factory()->pending()->create([
            'product_id' => $this->product->id,
            'customer_id' => Customer::factory()->create(),
            'rating' => 1,
        ]);

        // Sin auth.
        $response = $this->getJson(
            uri: "http://panda-estampados.com/api/v1/products/{$this->product->id}/reviews",
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath(path: 'meta.count', expect: 1);
        $response->assertJsonPath(path: 'meta.average_rating', expect: 5);
    }

    #[TestAttr]
    public function it_lists_only_own_reviews_for_customer(): void
    {
        $otherCustomer = Customer::factory()->create();
        ProductReview::factory()->create([
            'product_id' => $this->product->id,
            'customer_id' => $otherCustomer->id,
        ]);
        ProductReview::factory()->create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/account/reviews');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }
}
