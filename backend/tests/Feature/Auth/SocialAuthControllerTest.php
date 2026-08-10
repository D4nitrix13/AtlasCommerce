<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cobertura del SocialAuthController stub.
 *
 * El controller devuelve 501 hasta que se instale laravel/socialite
 * y se configuren credenciales OAuth. Estos tests congelan ese
 * contrato: cuando alguien implemente Socialite, debe actualizar
 * estos tests al nuevo comportamiento (redirect 302 a Google/Facebook).
 */
final class SocialAuthControllerTest extends TestCase
{
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
    }

    #[Test]
    public function redirect_with_google_returns_501(): void
    {
        $response = $this->get('/api/auth/social/google/redirect');

        $response->assertStatus(501);
        $response->assertJsonStructure(['message', 'hint']);
    }

    #[Test]
    public function redirect_with_facebook_returns_501(): void
    {
        $response = $this->get('/api/auth/social/facebook/redirect');

        $response->assertStatus(501);
        $response->assertJsonStructure(['message', 'hint']);
    }

    #[Test]
    public function redirect_with_unsupported_provider_returns_400(): void
    {
        $response = $this->get('/api/auth/social/twitter/redirect');

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => "Provider 'twitter' no soportado."]);
    }

    #[Test]
    public function callback_with_google_returns_501(): void
    {
        $response = $this->get('/api/auth/social/google/callback');

        $response->assertStatus(501);
    }

    #[Test]
    public function callback_with_unsupported_provider_returns_400(): void
    {
        $response = $this->get('/api/auth/social/twitter/callback');

        $response->assertStatus(400);
    }
}
