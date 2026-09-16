<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Models\Auth\User;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\Support\ProductQuestion;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests de Q&A de producto — endpoints cliente + público (RF-SUP-162).
 */
#[Group('support')]
#[Group('qa')]
final class ProductQuestionControllerTest extends TestCase
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
            uri: "http://panda-estampados.com/api/v1/products/{$this->product->id}/questions",
            data: ['question' => '¿Tienen stock en talla M?'],
        )->assertStatus(status: 401);
    }

    #[TestAttr]
    public function it_creates_question(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/v1/products/{$this->product->id}/questions",
            data: ['question' => '¿Tienen stock en talla M?'],
        );

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.question', expect: '¿Tienen stock en talla M?');
        $response->assertJsonPath(path: 'data.answer', expect: null);
    }

    #[TestAttr]
    public function it_validates_question_min_length(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/v1/products/{$this->product->id}/questions",
            data: ['question' => 'ok'],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['question']);
    }

    #[TestAttr]
    public function public_index_returns_only_answered(): void
    {
        ProductQuestion::factory()->create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'answer' => 'Sí, tenemos stock.',
            'answered_at' => now(),
        ]);
        ProductQuestion::factory()->create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'answer' => null,
            'answered_at' => null,
        ]);

        // Sin auth.
        $response = $this->getJson(
            uri: "http://panda-estampados.com/api/v1/products/{$this->product->id}/questions",
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[TestAttr]
    public function customer_lists_own_questions(): void
    {
        $otherCustomer = Customer::factory()->create();
        ProductQuestion::factory()->create([
            'product_id' => $this->product->id,
            'customer_id' => $otherCustomer->id,
        ]);
        ProductQuestion::factory()->create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/account/questions');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }
}
