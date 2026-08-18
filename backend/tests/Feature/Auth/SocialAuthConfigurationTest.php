<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cubre el comportamiento fail-closed del controller OAuth cuando
 * las credenciales del provider no están configuradas.
 *
 * El controller verifica `config('services.{provider}.client_id')` y
 * `config('services.{provider}.client_secret')` antes de delegar a
 * Socialite. Si alguno está vacío/null, corta con 503
 * `oauth_provider_not_configured` (R6 del proposal).
 *
 * El resto de la shape del redirect (302 a Google/Facebook con state)
 * está cubierto en `SocialAuthControllerTest` — acá solo nos
 * enfocamos en el path fail-closed.
 */
#[Group('social-auth')]
#[Group('controller')]
#[Group('fail-closed')]
final class SocialAuthConfigurationTest extends TestCase
{
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
    }

    #[Test]
    public function redirect_without_client_id_returns_503(): void
    {
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', 'test-secret');

        // Seteamos referer para que EnsureFrontendRequestsAreStateful
        // aplique el session middleware (sin esto, la session no se
        // inicia y el controller corta con oauth_state_required antes
        // de llegar al check de provider).
        $response = $this->get(
            uri: '/api/auth/social/google/redirect',
            headers: ['referer' => 'http://localhost/es/admin/auth/login'],
        );

        $response->assertStatus(503);
        $payload = $response->json();
        $this->assertSame(expected: 'oauth_provider_not_configured', actual: $payload['code']);
        $this->assertSame(expected: 'google', actual: $payload['provider']);
    }

    #[Test]
    public function redirect_without_client_secret_returns_503(): void
    {
        // Aunque redirect técnicamente solo necesita client_id para
        // construir el authorize URL, validamos ambos juntos para
        // que el usuario no se encuentre con un 500 genérico al
        // volver del provider (token exchange fallaría).
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', null);

        $response = $this->get(
            uri: '/api/auth/social/google/redirect',
            headers: ['referer' => 'http://localhost/es/admin/auth/login'],
        );

        $response->assertStatus(503);
        $payload = $response->json();
        $this->assertSame(expected: 'oauth_provider_not_configured', actual: $payload['code']);
    }

    #[Test]
    public function redirect_with_empty_string_client_id_returns_503(): void
    {
        // Edge case: .env.example viene con GOOGLE_CLIENT_ID=
        // (vacío). La verificación truthy rechaza string vacío igual
        // que null — fail-closed consistente.
        config()->set('services.google.client_id', '');
        config()->set('services.google.client_secret', 'test-secret');

        $response = $this->get(
            uri: '/api/auth/social/google/redirect',
            headers: ['referer' => 'http://localhost/es/admin/auth/login'],
        );

        $response->assertStatus(503);
        $response->assertJsonPath(path: 'code', expect: 'oauth_provider_not_configured');
    }

    #[Test]
    public function redirect_unsupported_provider_returns_400(): void
    {
        // Test movido desde SocialAuthControllerTest — vive acá
        // porque es un guard de config (provider no aprobado) más
        // que de comportamiento del redirect propiamente dicho.
        $response = $this->get(uri: '/api/auth/social/twitter/redirect');

        $response->assertStatus(400);
        $response->assertJsonFragment(data: ['message' => "Provider 'twitter' no soportado."]);
    }
}
