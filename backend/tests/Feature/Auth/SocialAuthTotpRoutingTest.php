<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Controllers\Api\Auth\SocialAuthController;
use App\Models\Auth\OtpChallenge;
use App\Models\Auth\SocialAccount;
use App\Models\Auth\TwoFactorSetting;
use App\Models\Auth\User;
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
 * Cubre el routing TOTP vía callback OAuth (spec A11).
 *
 * Spec A11:
 *   GIVEN User con `twoFactorSetting.enabled_at NOT NULL`
 *   WHEN callback OAuth procesado
 *   THEN 200 `{status: "2fa_required", challenge_token: <id>, method: "totp"}`
 *   AND NO se emite Bearer token.
 *
 * Por qué este test es un gap conocido: el happy-path callback
 * (`SocialAuthControllerTest::callback_supported_provider_returns_200_when_session_target_present`)
 * cubre el caso sin 2FA. La rama `hasTwoFactorEnabled()` en el
 * controller (líneas 198-201) no tenía cobertura dedicada —
 * ver `observation #217` del gate review. Este test la cierra.
 *
 * Defense-in-depth: el failure era la lección del incidente
 * original PR #7/#9 (controller olvidó `auth('web')->login()`).
 * El "no test = hidden bug surface" es exactamente este patrón.
 */
#[Group('social-auth')]
#[Group('controller')]
#[Group('totp')]
#[CoversClass(SocialAuthController::class)]
final class SocialAuthTotpRoutingTest extends TestCase
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
    public function callback_existing_totp_user_routes_through_verifyTotpLogin(): void
    {
        // Arrange — user con 2FA habilitada + SocialAccount binding.
        $user = User::factory()->create();
        TwoFactorSetting::query()->create([
            'user_id' => $user->id,
            'secret' => 'JBSWY3DPEHPK3PXP',
            'enabled_at' => now(),
        ]);

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'g-with-2fa',
        ]);

        // user_store para pasar el tenant gate (A9) — sin esto,
        // el callback corta con 422 antes de llegar a la rama TOTP.
        $storeId = $this->app->make(StoreContextService::class)->getStoreId();
        $user->stores()->attach($storeId, ['role_id' => null]);

        // Mock Socialite callback.
        $this->mockSocialiteUser(
            provider: 'google',
            providerId: 'g-with-2fa',
            email: 'totp-user@example.com',
            emailVerified: true,
        );

        // Act.
        $response = $this->get(
            uri: '/api/auth/social/google/callback',
            headers: ['referer' => 'http://localhost/es/admin/auth/login'],
        );

        // Assert — envelope 2fa_required idéntico al emitido por
        // `AuthController::login` para el login estándar (spec A11).
        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'challenge_token', 'method', 'expires_at']);
        $response->assertJsonPath(path: 'status', expect: '2fa_required');
        $response->assertJsonPath(path: 'method', expect: 'totp');

        // Sin Bearer token (R11 — el frontend DEBE llamar
        // `POST /api/auth/2fa/verify-totp-login` con el challenge_token
        // para obtener el token final).
        $response->assertJsonMissingPath('token');

        // El challenge existe en DB con el user correcto y está
        // vigente. Acopla explícitamente el `user_id` para detectar
        // si la implementación futura cambia el contrato.
        $this->assertDatabaseHas(table: 'otp_challenges', data: [
            'user_id' => $user->id,
            'type' => OtpChallenge::TYPE_TOTP,
            'consumed_at' => null,
        ]);
    }
}
