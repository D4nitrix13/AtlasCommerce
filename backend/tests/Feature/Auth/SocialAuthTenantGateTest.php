<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Controllers\Api\Auth\SocialAuthController;
use App\Models\Auth\SocialAccount;
use App\Models\Auth\User;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Services\Auth\SocialAccountService;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\MocksSocialite;
use Tests\TestCase;

/**
 * Cubre el comportamiento multi-tenant del callback OAuth (spec A9, A10).
 *
 * El callback debe respetar el mismo `hasAccessToStore()` que
 * `AuthController::login` para evitar account takeover cross-tenant
 * (R3 del proposal).
 */
#[Group('social-auth')]
#[Group('controller')]
#[Group('tenant')]
#[CoversClass(SocialAuthController::class)]
final class SocialAuthTenantGateTest extends TestCase
{
    use MocksSocialite;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::factory()->primary()->create();
        $store = Store::factory()->for($currency, 'currency')->create();
        StoreDomain::query()->create([
            'store_id' => $store->id,
            'domain' => 'localhost',
            'type' => 'domain',
            'base_path' => '/',
            'is_active' => true,
            'is_primary' => true,
        ]);
        $this->app->make(StoreContextService::class)->setStore($store);

        config()->set('services.google.client_id', 'test-google-client-id');
        config()->set('services.google.client_secret', 'test-google-client-secret');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function callback_existing_social_user_without_user_store_returns_422(): void
    {
        // User tiene SocialAccount para google/g-real PERO NO tiene
        // user_stores para el tenant activo → tenant gate falla.
        $user = User::factory()->create();
        SocialAccount::factory()
            ->forProvider(provider: 'google', providerUserId: 'g-real')
            ->for($user)
            ->create();

        $this->mockSocialiteUser(
            provider: 'google',
            providerId: 'g-real',
            email: 'cross-tenant@example.com',
            emailVerified: true,
        );

        $response = $this->get(
            uri: '/api/auth/social/google/callback',
            headers: ['referer' => 'http://localhost/es/admin/auth/login'],
        );

        $response->assertStatus(422);
        $response->assertJsonFragment(data: ['message' => 'No tienes acceso a esta tienda.']);
    }

    #[Test]
    public function callback_existing_social_user_with_user_store_returns_200(): void
    {
        // User tiene SocialAccount + user_store para el tenant activo
        // → happy path, 200 + token.
        $storeId = $this->app->make(StoreContextService::class)->getStoreId();
        $user = User::factory()->create();
        SocialAccount::factory()
            ->forProvider(provider: 'google', providerUserId: 'g-real')
            ->for($user)
            ->create();
        $user->stores()->attach($storeId, ['role_id' => null]);

        $this->mockSocialiteUser(
            provider: 'google',
            providerId: 'g-real',
            email: 'allowed@example.com',
            emailVerified: true,
        );

        $response = $this->get(
            uri: '/api/auth/social/google/callback',
            headers: ['referer' => 'http://localhost/es/admin/auth/login'],
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }

    #[Test]
    public function callback_new_customer_creates_user_store_for_active_tenant(): void
    {
        // OAuth-created user (A6) debe crear customer_store + user_store
        // para el tenant activo (P-C policy — sin user_store, el tenant
        // gate del callback lo bloquea con 422).
        $this->mockSocialiteUser(
            provider: 'google',
            providerId: 'g-new-customer',
            email: 'newcustomer@example.com',
            emailVerified: true,
        );

        $response = $this->get(
            uri: '/api/auth/social/google/callback',
            headers: ['referer' => 'http://localhost/es/account/auth/login'],
        );

        $response->assertStatus(200);

        $user = User::query()->where('email', 'newcustomer@example.com')->firstOrFail();
        $this->assertSame(expected: 1, actual: $user->stores()->count());
    }
}
