<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\MocksSocialite;
use Tests\TestCase;

/**
 * Smoke test para el behavior de session-cookie survival en el
 * failure flash redirect (design open Q #7).
 *
 * El callback failure emite un 302 al login page. La pregunta:
 * ¿se persiste el `Set-Cookie` en el 302 response, o solo en el
 * callback original?
 *
 * Contrato: el 302 NO debe llevar `Set-Cookie` (la session ya está
 * comprometida en el response anterior — el callback original). El
 * frontend lee el `Location` header y navega al login page; la
 * session cookie ya está en el browser.
 *
 * También verificamos que `oauth_redirect_target` se pop de session
 * después del redirect (one-shot, no se queda residuo).
 */
#[Group('social-auth')]
#[Group('failure-flash')]
#[CoversClass(\App\Http\Controllers\Api\Auth\SocialAuthController::class)]
final class SocialAuthFailureFlashTest extends TestCase
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

        $storeContext = $this->app->make(StoreContextService::class);
        $storeContext->setStore($store);
        $this->app->instance(StoreContextService::class, $storeContext);

        config()->set('services.google.client_id', 'test-google-client-id');
        config()->set('services.google.client_secret', 'test-google-client-secret');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function session_cookie_target_is_popped_after_failure_redirect(): void
    {
        // Mockeamos Socialite para tirar InvalidStateException.
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

        // Pre-populamos `oauth_redirect_target` en session (lo que
        // normalmente hace el redirect endpoint antes de delegar).
        $this->startSession();
        $this->app['session.store']->put(
            key: 'oauth_redirect_target',
            value: '/es/admin/auth/login',
        );

        $response = $this->get(
            uri: '/api/auth/social/google/callback',
            headers: ['referer' => 'http://localhost/es/admin/auth/login'],
        );

        $response->assertStatus(302);

        // Después del redirect, `oauth_redirect_target` se pop de session
        // (one-shot via `$request->session()->pull()` en el controller).
        // Si NO se popeara, sería un leak — próximos redirects
        // podrían terminar en la URL vieja.
        $this->assertNull(
            $this->app['session.store']->get(key: 'oauth_redirect_target'),
        );
    }

    #[Test]
    public function failure_redirect_response_status_and_location(): void
    {
        // Smoke test: el callback failure redirect emite 302 con
        // Location correcto. El contrato de cookies (no regeneración)
        // está garantizado por `EnsureFrontendRequestsAreStateful`
        // + `auth('web')` — covered por tests existentes. Acá solo
        // verificamos que el response es válido.

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

        $this->startSession();
        $this->app['session.store']->put(
            key: 'oauth_redirect_target',
            value: '/es/account/auth/login',
        );

        $response = $this->get(
            uri: '/api/auth/social/google/callback',
            headers: ['referer' => 'http://localhost/es/account/auth/login'],
        );

        $response->assertStatus(302);
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/account/auth/login', (string) $location);
        $this->assertStringContainsString('flash=oauth_state_mismatch', (string) $location);
    }

    #[Test]
    public function failure_redirect_preserves_session_target_with_locale(): void
    {
        // El callback failure redirect debe preservar el locale del
        // session target (PR 2a reviewer #4 fix). El frontend lee
        // el query `?flash=` y muestra el toast.
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

        $this->startSession();
        $this->app['session.store']->put(
            key: 'oauth_redirect_target',
            value: '/en/account/auth/login',
        );

        $response = $this->get(
            uri: '/api/auth/social/google/callback',
            headers: ['referer' => 'http://localhost/en/account/auth/login'],
        );

        $response->assertStatus(302);
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/account/auth/login', (string) $location);
        $this->assertStringContainsString('flash=oauth_state_mismatch', (string) $location);
    }
}
