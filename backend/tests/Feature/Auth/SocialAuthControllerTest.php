<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Resources\Auth\OAuthErrorResource;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\MocksSocialite;
use Tests\TestCase;

/**
 * Cubre la shape del redirect endpoint del flujo OAuth.
 *
 * PR 1 implementa `redirect()` completo:
 *   - provider ∈ SUPPORTED
 *   - $request->hasSession() (400 oauth_state_required si no)
 *   - config services.{provider}.client_id + client_secret (503 si no)
 *   - Socialite::driver()->redirectUrl(perHost)->redirect() → 302
 *
 * Los tests de callback (spec A3-A12) llegan en PR 2 cuando
 * `findOrCreateFromProvider` esté completo.
 */
#[Group('social-auth')]
#[Group('controller')]
#[CoversClass(\App\Http\Controllers\Api\Auth\SocialAuthController::class)]
final class SocialAuthControllerTest extends TestCase
{
    use MocksSocialite;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tienda + dominio para que el middleware `store.domain`
        // resuelva el contexto (las rutas /api/auth/social/* están
        // dentro de ese grupo en routes/api.php).
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

        // Para los tests que esperan 302 (redirect), mockeamos
        // client_id + client_secret como si el .env estuviera
        // configurado. Los tests fail-closed en
        // SocialAuthConfigurationTest los dejan en null.
        config()->set('services.google.client_id', 'test-google-client-id');
        config()->set('services.google.client_secret', 'test-google-client-secret');
        config()->set('services.facebook.client_id', 'test-facebook-client-id');
        config()->set('services.facebook.client_secret', 'test-facebook-client-secret');
    }

    #[Test]
    public function redirect_google_returns_302_to_provider(): void
    {
        $this->mockSocialiteRedirect(
            provider: 'google',
            url: 'https://accounts.google.com/o/oauth2/auth?state=abc123',
        );

        $response = $this->get(
            uri: '/api/auth/social/google/redirect',
            headers: ['referer' => 'http://localhost/es/admin/auth/login'],
        );

        $response->assertStatus(302);
        $response->assertRedirect(uri: 'https://accounts.google.com/o/oauth2/auth?state=abc123');
    }

    #[Test]
    public function redirect_facebook_returns_302_to_provider(): void
    {
        $this->mockSocialiteRedirect(
            provider: 'facebook',
            url: 'https://www.facebook.com/v18.0/dialog/oauth?state=xyz789',
        );

        $response = $this->get(
            uri: '/api/auth/social/facebook/redirect',
            headers: ['referer' => 'http://localhost/es/account/auth/login'],
        );

        $response->assertStatus(302);
        $response->assertRedirect(uri: 'https://www.facebook.com/v18.0/dialog/oauth?state=xyz789');
    }

    #[Test]
    public function redirect_unsupported_provider_returns_400(): void
    {
        // No se mockea Socialite — el controller corta antes de
        // llegar al facade (provider guard). El test verifica que
        // NUNCA se llama Socialite porque la respuesta es 400
        // inmediata.
        $response = $this->get(uri: '/api/auth/social/github/redirect');

        $response->assertStatus(400);
        $response->assertJsonFragment(data: ['message' => "Provider 'github' no soportado."]);
    }

    #[Test]
    public function redirect_without_session_cookie_returns_400_oauth_state_required(): void
    {
        // NO seteamos referer/origin — simulamos una request sin
        // cookie de sesión. Defense-in-depth (R2 del proposal): el
        // redirect endpoint debe cortar temprano si no hay session
        // antes de delegar a Socialite.
        $response = $this->get(uri: '/api/auth/social/google/redirect');

        $response->assertStatus(400);
        $payload = $response->json();
        $this->assertSame(expected: 'oauth_state_required', actual: $payload['code']);
        $this->assertSame(expected: 'google', actual: $payload['provider']);
    }

    #[Test]
    public function callback_unsupported_provider_returns_400(): void
    {
        // PR 1: callback sigue siendo 501. Solo validamos el guard
        // del provider (igual semántica que redirect).
        $response = $this->get(uri: '/api/auth/social/github/callback');

        $response->assertStatus(400);
        $response->assertJsonFragment(data: ['message' => "Provider 'github' no soportado."]);
    }

    #[Test]
    public function callback_supported_provider_returns_200_when_session_target_present(): void
    {
        // PR 2b: callback completo. Con un SocialiteUser mockeado +
        // session target + config + store context, debe resolver el
        // user y devolver 200 con token.
        $this->mockSocialiteUser(
            provider: 'google',
            providerId: 'g-new-customer',
            email: 'new-customer@example.com',
            emailVerified: true,
        );

        $response = $this->get(
            uri: '/api/auth/social/google/callback',
            headers: ['referer' => 'http://localhost/es/account/auth/login'],
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
