<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Storefront;

use App\Models\Auth\User as UserModel;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\Support\ProductReview;
use App\Models\Support\ProductReviewLike;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests de ProductReviewLikeController (T8 del plan PDP reseñas).
 *
 * Cubre:
 *  - POST /storefront/reviews/{review}/like: auth, idempotencia (201 vs 200)
 *  - DELETE /storefront/reviews/{review}/like: auth, idempotencia
 *  - GET /storefront/reviews/{review}/like: público, count + liked-by-current
 *  - 404 en reseña inexistente para los 3 verbos
 */
#[Group('product-review-likes')]
final class ProductReviewLikeTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $user;

    private Customer $customer;

    private ProductReview $review;

    private Product $product;

    private Store $store;

    private string $host = 'panda-estampados.test';

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($currency, 'currency')->create();

        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => $this->host,
            'type' => 'domain',
            'base_path' => '/',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $this->user = UserModel::factory()->create();
        $this->customer = Customer::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $this->customer->stores()->attach($this->store->id);

        $this->product = Product::factory()->for($this->store->currency, 'currency')->create();

        $this->review = ProductReview::factory()->create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
        ]);
    }

    #[TestAttr]
    public function unauthenticated_like_returns_401(): void
    {
        $response = $this->postJson(uri: $this->likeUrl());

        $response->assertStatus(status: 401);
    }

    #[TestAttr]
    public function authenticated_like_creates_row_and_returns_201(): void
    {
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(uri: $this->likeUrl());

        $response->assertStatus(status: 201);
        $response->assertExactJson(data: [
            'liked' => true,
            'count' => 1,
        ]);

        $this->assertDatabaseHas(table: 'product_review_likes', data: [
            'product_review_id' => $this->review->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    #[TestAttr]
    public function authenticated_like_twice_returns_200_with_count_idempotent(): void
    {
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $this->postJson(uri: $this->likeUrl())->assertStatus(status: 201);

        // Segundo POST es idempotente: row ya existe, status 200, count sigue 1.
        $response = $this->postJson(uri: $this->likeUrl());

        $response->assertStatus(status: 200);
        $response->assertExactJson(data: [
            'liked' => true,
            'count' => 1,
        ]);

        $this->assertSame(
            expected: 1,
            actual: ProductReviewLike::query()
                ->where(column: 'product_review_id', operator: '=', value: $this->review->id)
                ->count(),
        );
    }

    #[TestAttr]
    public function authenticated_unlike_removes_row_and_returns_200(): void
    {
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        ProductReviewLike::query()->create([
            'product_review_id' => $this->review->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->deleteJson(uri: $this->likeUrl());

        $response->assertStatus(status: 200);
        $response->assertExactJson(data: [
            'liked' => false,
            'count' => 0,
        ]);

        $this->assertDatabaseMissing(table: 'product_review_likes', data: [
            'product_review_id' => $this->review->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    #[TestAttr]
    public function authenticated_unlike_when_no_like_returns_200_idempotent(): void
    {
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        // No hay like previo — DELETE debe responder 200 con count 0
        // (idempotente: borrar algo que no existe no es error).
        $response = $this->deleteJson(uri: $this->likeUrl());

        $response->assertStatus(status: 200);
        $response->assertExactJson(data: [
            'liked' => false,
            'count' => 0,
        ]);
    }

    #[TestAttr]
    public function show_returns_count_for_anyone(): void
    {
        // Creamos 3 likes de customers distintos para validar count.
        ProductReviewLike::factory()->count(3)->create([
            'product_review_id' => $this->review->id,
        ]);

        // Público: sin auth, igual expone el count.
        $response = $this->getJson(uri: $this->likeUrl());

        $response->assertStatus(status: 200);
        $response->assertJsonPath(path: 'count', expect: 3);
        $response->assertJsonPath(path: 'liked', expect: false);
    }

    #[TestAttr]
    public function show_returns_liked_true_when_authenticated_user_liked(): void
    {
        ProductReviewLike::query()->create([
            'product_review_id' => $this->review->id,
            'customer_id' => $this->customer->id,
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->getJson(uri: $this->likeUrl());

        $response->assertStatus(status: 200);
        $response->assertExactJson(data: [
            'liked' => true,
            'count' => 1,
        ]);
    }

    #[TestAttr]
    public function show_returns_liked_false_when_authenticated_user_not_liked(): void
    {
        // Like de OTRO customer — el user autenticado NO ha likedeado.
        $otherCustomer = Customer::factory()->create();
        ProductReviewLike::query()->create([
            'product_review_id' => $this->review->id,
            'customer_id' => $otherCustomer->id,
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->getJson(uri: $this->likeUrl());

        $response->assertStatus(status: 200);
        $response->assertJsonPath(path: 'liked', expect: false);
        $response->assertJsonPath(path: 'count', expect: 1);
    }

    #[TestAttr]
    public function like_nonexistent_review_returns_404(): void
    {
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        // ID de reseña que no existe. El route-model-binding lanza 404.
        $this->postJson(uri: $this->likeUrl(999_999))->assertStatus(status: 404);
        $this->deleteJson(uri: $this->likeUrl(999_999))->assertStatus(status: 404);
    }

    #[TestAttr]
    public function unlike_nonexistent_review_returns_404(): void
    {
        $this->deleteJson(uri: $this->likeUrl(999_999))->assertStatus(status: 404);
    }

    /**
     * Helper: construye la URL absoluta con host para que el middleware
     * `store.domain` resuelva la tienda activa.
     */
    private function likeUrl(?int $reviewId = null): string
    {
        $id = $reviewId ?? $this->review->id;

        return "http://{$this->host}/api/v1/storefront/reviews/{$id}/like";
    }
}
