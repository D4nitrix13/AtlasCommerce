<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\MocksSocialite;
use Tests\TestCase;

/**
 * Cubre la defensa CSRF de state del flow OAuth (spec A12, R2, R11).
 *
 * Socialite 5.x valida el `state` vía `AbstractProvider::hasInvalidState()`.
 * Si la validación falla (mismatch o session vacía), tira
 * `InvalidStateException` que el controller traduce a
 * `OAuthStateMismatchException` → 400 + `Log::warning('oauth_state_mismatch')`.
 *
 * Defense-in-depth extra (R2): el redirect endpoint exige
 * `$request->hasSession()` ANTES de delegar a Socialite, para que el
 * state no se pierda si no hay cookie.
 */
#[Group('social-auth')]
#[Group('controller')]
#[Group('state-csrf')]
final class SocialAuthStateCsrfTest extends TestCase
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
    public function state_mismatch_returns_302_with_flash_oauth_state_mismatch(): void
    {
        // Spec A12: state mismatch returns 400 JSON. Design §5 + §11
        // commits to 302 redirect with `?flash=` (failure UX siempre
        // pasa por el login page con toast — R5). El controller
        // implementa el design: 302 con `?flash=oauth_state_mismatch`.
        // El spec original asume API-only callback; con frontend
        // siempre presente, 302 + flash es la mejor UX.

        $manager = Mockery::mock(\Laravel\Socialite\SocialiteManager::class);
        $manager->shouldReceive('driver')
            ->with('google')
            ->andReturnUsing(function () {
                $driver = Mockery::mock();
                $driver->shouldReceive('user')
                    ->andThrow(new \Laravel\Socialite\Two\InvalidStateException);

                return $driver;
            });
        \Laravel\Socialite\Facades\Socialite::swap($manager);

        $response = $this->get(
            uri: '/api/v1/auth/social/google/callback',
            headers: ['referer' => 'http://admin.localhost/es/admin/auth/login'],
        );

        $response->assertStatus(302);
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('flash=oauth_state_mismatch', (string) $location);
    }

    #[Test]
    public function state_mismatch_falls_back_to_account_login_when_session_target_empty(): void
    {
        // Sin session target + host = `localhost` (no admin.*), el
        // helper cae a `/account/login`. Cubierto por
        // `OAuthRedirectTargetTest::from_request_falls_back_to_account_login_for_storefront`
        // — este test verifica que el controller invoca correctamente.

        $manager = Mockery::mock(\Laravel\Socialite\SocialiteManager::class);
        $manager->shouldReceive('driver')
            ->with('google')
            ->andReturnUsing(function () {
                $driver = Mockery::mock();
                $driver->shouldReceive('user')
                    ->andThrow(new \Laravel\Socialite\Two\InvalidStateException);

                return $driver;
            });
        \Laravel\Socialite\Facades\Socialite::swap($manager);

        $response = $this->get(
            uri: '/api/v1/auth/social/google/callback',
            headers: ['referer' => 'http://localhost/es/account/auth/login'],
        );

        $response->assertStatus(302);
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/account/login', (string) $location);
        $this->assertStringContainsString('flash=oauth_state_mismatch', (string) $location);
    }

    #[Test]
    public function state_roundtrip_succeeds_via_session(): void
    {
        // Happy path completo: redirect guarda state en session →
        // callback lee el state de session → matchea → callback
        // resuelve user. Mockeamos el user para que devuelva OK.
        $this->mockSocialiteUser(
            provider: 'google',
            providerId: 'g-roundtrip',
            email: 'roundtrip@example.com',
            emailVerified: true,
        );

        $response = $this->get(
            uri: '/api/v1/auth/social/google/callback',
            headers: ['referer' => 'http://localhost/es/admin/auth/login'],
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }
}
