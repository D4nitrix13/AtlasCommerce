<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Exceptions\SocialAuth\OAuthAdminSignupDisabledException;
use App\Exceptions\SocialAuth\OAuthEmailConflictException;
use App\Exceptions\SocialAuth\OAuthEmailUnverifiedException;
use App\Models\Auth\SocialAccount;
use App\Models\Auth\User;
use App\Models\Customers\Customer;
use App\Models\Stores\CustomerStore;
use App\Models\Stores\Store;
use App\Services\Auth\SocialAccountService;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cubre el flujo completo de `findOrCreateFromProvider` (spec A3-A8).
 *
 * Cada test usa un `SocialiteUser` real (no Mockery) — es la
 * representación más fiel del payload que entrega el provider.
 * `email_verified` se controla via `setRawAttributes` para simular
 * cada branch (R9).
 */
#[Group('social-auth')]
#[Group('service')]
#[CoversClass(SocialAccountService::class)]
final class SocialAccountServiceFullTest extends TestCase
{
    use RefreshDatabase;

    private SocialAccountService $service;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Register StoreContextService as a singleton so that
        // `app->make()` (en setUp) y la resolución del container
        // para SocialAccountService (en tests que llaman
        // findOrCreateFromProvider) compartan la misma instancia.
        $storeContext = $this->app->make(StoreContextService::class);
        $this->app->instance(StoreContextService::class, $storeContext);

        $this->store = Store::factory()->create();
        $storeContext->setStore($this->store);

        $this->service = $this->app->make(SocialAccountService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ---------------------------------------------------------------------
    // Scenario A3 — existing social_account wins, no writes
    // ---------------------------------------------------------------------

    #[Test]
    public function find_or_create_returns_bound_user_when_social_account_exists(): void
    {
        $existingUser = User::factory()->create();
        SocialAccount::factory()
            ->forProvider(provider: 'google', providerUserId: 'g-real')
            ->for($existingUser)
            ->create();

        $oauthUser = $this->makeSocialiteUser(
            providerId: 'g-real',
            email: 'new-email@example.com',  // different email — no conflict possible
            emailVerified: true,
        );

        $userCountBefore = User::count();
        $socialCountBefore = SocialAccount::count();

        $result = $this->service->findOrCreateFromProvider(
            providerUser: $oauthUser,
            host: 'panda-estampados.com',
            provider: 'google',
        );

        $this->assertSame(expected: $existingUser->id, actual: $result->id);
        $this->assertSame(expected: $userCountBefore, actual: User::count());
        $this->assertSame(expected: $socialCountBefore, actual: SocialAccount::count());
    }

    // ---------------------------------------------------------------------
    // Scenario A4 — email conflict (R1)
    // ---------------------------------------------------------------------

    #[Test]
    public function find_or_create_throws_email_conflict_when_user_exists_with_same_email(): void
    {
        User::factory()->create(['email' => 'carlos@example.com']);

        $oauthUser = $this->makeSocialiteUser(
            providerId: 'g-new',
            email: 'carlos@example.com',
            emailVerified: true,
        );

        $userCountBefore = User::count();
        $socialCountBefore = SocialAccount::count();

        try {
            $this->service->findOrCreateFromProvider(
                providerUser: $oauthUser,
                host: 'panda-estampados.com',
                provider: 'google',
            );
            $this->fail('Expected OAuthEmailConflictException');
        } catch (OAuthEmailConflictException $e) {
            $this->assertSame(expected: 'oauth_email_conflict', actual: $e->oauthCode());
            $this->assertSame(expected: 'carlos@example.com', actual: $e->extras()['email']);
        }

        $this->assertSame(expected: $userCountBefore, actual: User::count());
        $this->assertSame(expected: $socialCountBefore, actual: SocialAccount::count());
    }

    // ---------------------------------------------------------------------
    // Scenario A5 — email_unverified (Google only — R9)
    // ---------------------------------------------------------------------

    #[Test]
    public function find_or_create_throws_unverified_for_google_email_verified_false(): void
    {
        $oauthUser = $this->makeSocialiteUser(
            providerId: 'g-unverified',
            email: 'new@example.com',
            emailVerified: false,
            provider: 'google',
        );

        try {
            $this->service->findOrCreateFromProvider(
                providerUser: $oauthUser,
                host: 'panda-estampados.com',
                provider: 'google',
            );
            $this->fail('Expected OAuthEmailUnverifiedException');
        } catch (OAuthEmailUnverifiedException $e) {
            $this->assertSame(expected: 'oauth_email_unverified', actual: $e->oauthCode());
            $this->assertSame(expected: 'google', actual: $e->extras()['provider']);
        }

        $this->assertSame(expected: 0, actual: User::count());
        $this->assertSame(expected: 0, actual: SocialAccount::count());
    }

    // ---------------------------------------------------------------------
    // Scenario A6 — atomic create (happy path, P-C customer + CustomerStore)
    // ---------------------------------------------------------------------

    #[Test]
    public function find_or_create_atomically_creates_user_customer_membership_and_social(): void
    {
        $oauthUser = $this->makeSocialiteUser(
            providerId: 'g-new-customer',
            email: 'newcustomer@example.com',
            emailVerified: true,
            name: 'Carlos Mendoza',
        );

        $user = $this->service->findOrCreateFromProvider(
            providerUser: $oauthUser,
            host: 'panda-estampados.com',
            provider: 'google',
        );

        // Exactly one row per table.
        $this->assertSame(expected: 1, actual: User::count());
        $this->assertSame(expected: 1, actual: Customer::count());
        $this->assertSame(expected: 1, actual: CustomerStore::count());
        $this->assertSame(expected: 1, actual: SocialAccount::count());

        // User shape.
        $this->assertSame(expected: 'newcustomer@example.com', actual: $user->email);
        $this->assertNotNull($user->email_verified_at);
        // Password is bcrypt-hashed random string (R7). Verificamos
        // que (a) NO es vacío, (b) NO es el valor plain text,
        // (c) matchea formato bcrypt ($2y$).
        $this->assertNotEmpty($user->password);
        $this->assertStringStartsWith('$2y$', (string) $user->password);

        // Customer shape.
        $customer = Customer::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(expected: 'Carlos', actual: $customer->first_name);
        $this->assertSame(expected: 'Mendoza', actual: $customer->last_name);
        $this->assertSame(expected: 'newcustomer@example.com', actual: $customer->email);

        // CustomerStore bound to the active store.
        $binding = CustomerStore::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(expected: $this->store->id, actual: $binding->store_id);

        // SocialAccount shape.
        $social = SocialAccount::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(expected: 'google', actual: $social->provider);
        $this->assertSame(expected: 'g-new-customer', actual: $social->provider_user_id);
    }

    // ---------------------------------------------------------------------
    // Scenario A7 — atomic rollback (FK failure on customer_stores)
    // ---------------------------------------------------------------------

    #[Test]
    public function find_or_create_rolls_back_transaction_when_customer_store_insert_fails(): void
    {
        $oauthUser = $this->makeSocialiteUser(
            providerId: 'g-rollback',
            email: 'rollback@example.com',
            emailVerified: true,
        );

        // Forzamos FK failure sobre customer_stores: simulamos que
        // el store context devuelve un store_id inexistente.
        // `id` no está en fillable de Store — usamos setRawAttributes
        // para bypassear y setear el id sin persistir.
        $ghostStore = new Store();
        $ghostStore->setRawAttributes(['id' => 999999]);
        $this->app->make(StoreContextService::class)->setStore($ghostStore);

        try {
            $this->service->findOrCreateFromProvider(
                providerUser: $oauthUser,
                host: 'panda-estampados.com',
                provider: 'google',
            );
            $this->fail('Expected QueryException for FK failure');
        } catch (\Illuminate\Database\QueryException $e) {
            // Esperado: violación de FK sobre customer_stores.store_id.
            $this->assertStringContainsString('customer_stores', $e->getMessage());
        }

        // Cero filas en users/customers/social_accounts — todo rolled back.
        $this->assertSame(expected: 0, actual: User::count());
        $this->assertSame(expected: 0, actual: Customer::count());
        $this->assertSame(expected: 0, actual: SocialAccount::count());
    }

    // ---------------------------------------------------------------------
    // Scenario A8 — admin signup disabled (P-C)
    // ---------------------------------------------------------------------

    #[Test]
    public function find_or_create_throws_admin_signup_disabled_on_admin_subdomain_with_no_user_match(): void
    {
        $oauthUser = $this->makeSocialiteUser(
            providerId: 'g-admin-attempt',
            email: 'new.staff@example.com',
            emailVerified: true,
        );

        try {
            $this->service->findOrCreateFromProvider(
                providerUser: $oauthUser,
                host: 'admin.panda-estampados.com',
                provider: 'google',
            );
            $this->fail('Expected OAuthAdminSignupDisabledException');
        } catch (OAuthAdminSignupDisabledException $e) {
            $this->assertSame(expected: 'oauth_admin_signup_disabled', actual: $e->oauthCode());
        }

        $this->assertSame(expected: 0, actual: User::count());
    }

    // ---------------------------------------------------------------------
    // Scenario R9 — Facebook treated as verified when email present
    // ---------------------------------------------------------------------

    #[Test]
    public function find_or_create_treats_facebook_as_verified_when_email_present(): void
    {
        // Facebook no expone email_verified — el provider user no
        // tiene `user['email_verified']`. El servicio debe tratar
        // email presente como verificado.
        $oauthUser = $this->makeSocialiteUser(
            providerId: 'fb-1',
            email: 'fbuser@example.com',
            emailVerified: null,  // Facebook: no aplica
            provider: 'facebook',
        );

        $user = $this->service->findOrCreateFromProvider(
            providerUser: $oauthUser,
            host: 'kitsune.com',
            provider: 'facebook',
        );

        $this->assertSame(expected: 'fbuser@example.com', actual: $user->email);
        $this->assertSame(expected: 1, actual: SocialAccount::query()
            ->where('provider', 'facebook')
            ->count());
    }

    #[Test]
    public function find_or_create_rejects_facebook_without_email(): void
    {
        // Facebook sin email → no se puede verificar nada. Tratar
        // como unverified para safety (no crear cuenta sin email).
        $oauthUser = $this->makeSocialiteUser(
            providerId: 'fb-no-email',
            email: null,
            emailVerified: null,
            provider: 'facebook',
        );

        try {
            $this->service->findOrCreateFromProvider(
                providerUser: $oauthUser,
                host: 'kitsune.com',
                provider: 'facebook',
            );
            $this->fail('Expected OAuthEmailUnverifiedException');
        } catch (OAuthEmailUnverifiedException $e) {
            $this->assertSame(expected: 'oauth_email_unverified', actual: $e->oauthCode());
        }
    }

    // ---------------------------------------------------------------------
    // createSocialAccount — task 2.1 acceptance
    // ---------------------------------------------------------------------

    #[Test]
    public function create_social_account_inserts_unique_composite_row(): void
    {
        $user = User::factory()->create();
        $oauthUser = $this->makeSocialiteUser(
            providerId: 'g-create-1',
            email: 'create1@example.com',
            emailVerified: true,
        );

        $social = $this->service->createSocialAccount(
            user: $user,
            providerUser: $oauthUser,
            provider: 'google',
        );

        $this->assertSame(expected: $user->id, actual: $social->user_id);
        $this->assertSame(expected: 'google', actual: $social->provider);
        $this->assertSame(expected: 'g-create-1', actual: $social->provider_user_id);
        $this->assertTrue($social->exists);

        // Segunda inserción con mismo (provider, provider_user_id) debe
        // fallar por unique composite (R12).
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->service->createSocialAccount(
            user: $user,
            providerUser: $oauthUser,
            provider: 'google',
        );
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Construye un `SocialiteUser` con email_verified controlable.
     *
     * Google lo expone en `$user->user['email_verified']`; Facebook
     * no lo expone (pasamos `null` y el servicio decide según email
     * presente — R9).
     */
    private function makeSocialiteUser(
        string $providerId,
        ?string $email,
        ?bool $emailVerified,
        string $name = 'Test User',
        string $provider = 'google',
    ): SocialiteUser {
        $user = new SocialiteUser();
        $user->id = $providerId;
        $user->nickname = null;
        $user->name = $name;
        $user->email = $email;
        $user->avatar = null;

        // AbstractUser::setRaw() recibe el array completo del provider
        // (lo que devuelve el HTTP response crudo). Google expone
        // `email_verified` al top level de ese payload — discovery
        // #210. Facebook no lo expone.
        $rawAttributes = [
            'id' => $providerId,
            'nickname' => null,
            'name' => $name,
            'email' => $email,
            'avatar' => null,
            'avatar_original' => null,
        ];
        if ($provider === 'google') {
            $rawAttributes['email_verified'] = $emailVerified ?? false;
        }

        $user->setRaw(user: $rawAttributes);

        return $user;
    }
}
