<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\User;
use App\Services\Auth\RecoveryCodeService;
use Database\Seeders\DefaultAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integration test del flow recovery codes vía HTTP.
 *
 * Cubre:
 *  - forgot-password con user preferred_recovery_method='2fa' → 422.
 *  - forgot-password con user preferred_recovery_method='recovery_codes'
 *    → 422 (debe usar reset-password).
 *  - reset-password con method=recovery_codes + code válido → 200 + token.
 *  - reset-password con method=recovery_codes + code usado → 422.
 *  - reset-password con method=recovery_codes + user sin codes → 422.
 *  - reset-password con method=recovery_codes + user con preferred=email
 *    → 422 (method no coincide con preferred).
 *  - reset-password con method=email (default) sigue funcionando (regression).
 */
#[Group('auth')]
#[Group('http')]
#[Group('integration')]
#[Group('recovery-codes')]
final class RecoveryCodeFlowTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://admin.panda-estampados.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DefaultAdminSeeder::class);

        // El RateLimiter usa cache (shared entre tests en la misma
        // corrida phpunit). Sin limpiar, después de 5 hits el throttle
        // bloquea todos los tests subsiguientes. Limpiamos en setUp.
        RateLimiter::clear(key: 'forgot-password:2fauser@panda-estampados.com');
        RateLimiter::clear(key: 'forgot-password:rccuser@panda-estampados.com');
        RateLimiter::clear(key: 'forgot-password:emailuser@panda-estampados.com');
        RateLimiter::clear(key: 'forgot-password:admin@panda-estampados.com');
        RateLimiter::clear(key: 'reset-password:127.0.0.1');
    }

    /**
     * Helper: crea un user con 10 recovery codes y lo asocia a la
     * tienda panda-estampados. Retorna el user y el array de codes
     * en plain text (el primero se usa en los tests).
     */
    private function userWithRecoveryCodes(string $email, string $password = 'password'): array
    {
        $user = User::factory()->create(attributes: [
            'email' => $email,
            'password' => $password,
            'is_active' => true,
            'preferred_recovery_method' => 'recovery_codes',
        ]);

        $store = \App\Models\Stores\Store::query()
            ->where(column: 'slug', operator: '=', value: 'panda-estampados')
            ->firstOrFail();
        $user->stores()->syncWithoutDetaching(ids: [$store->id]);

        $codes = app(abstract: RecoveryCodeService::class)->generateFor(user: $user, count: 10);

        return ['user' => $user, 'codes' => $codes];
    }

    #[Test]
    public function forgot_password_with_2fa_preference_returns_422(): void
    {
        $user = User::factory()->create(attributes: [
            'email' => '2fauser@panda-estampados.com',
            'is_active' => true,
            'preferred_recovery_method' => '2fa',
        ]);
        $store = \App\Models\Stores\Store::query()
            ->where(column: 'slug', operator: '=', value: 'panda-estampados')
            ->firstOrFail();
        $user->stores()->syncWithoutDetaching(ids: [$store->id]);

        $response = $this->postJson(uri: self::HOST . '/api/auth/forgot-password', data: [
            'email' => $user->email,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['method']);
        $this->assertStringContainsString(
            needle: 'autenticación de dos factores',
            haystack: $response->json(key: 'errors.method.0'),
        );
    }

    #[Test]
    public function forgot_password_with_recovery_codes_preference_returns_422(): void
    {
        ['user' => $user] = $this->userWithRecoveryCodes('rccuser@panda-estampados.com');

        $response = $this->postJson(uri: self::HOST . '/api/auth/forgot-password', data: [
            'email' => $user->email,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['method']);
        $this->assertStringContainsString(
            needle: 'recovery codes',
            haystack: $response->json(key: 'errors.method.0'),
        );

        // No debe haberse creado ningún password_reset_token.
        $this->assertDatabaseMissing(table: 'password_reset_tokens', data: [
            'email' => $user->email,
        ]);
    }

    #[Test]
    public function reset_password_with_valid_recovery_code_returns_token(): void
    {
        ['user' => $user, 'codes' => $codes] = $this->userWithRecoveryCodes('rccuser@panda-estampados.com');
        $code = $codes[0];

        $response = $this->postJson(uri: self::HOST . '/api/auth/reset-password', data: [
            'email' => $user->email,
            'method' => 'recovery_codes',
            'code' => $code,
            'password' => 'BrandNewPassword1234',
            'password_confirmation' => 'BrandNewPassword1234',
            'device_name' => 'integration-test',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'user' => ['id', 'email']]);

        // Password actualizado en DB.
        $user->refresh();
        $this->assertTrue(
            condition: Hash::check(value: 'BrandNewPassword1234', hashedValue: $user->password),
        );

        // Code marcado como usado.
        $this->assertSame(
            expected: 9,
            actual: app(abstract: RecoveryCodeService::class)->availableCount(user: $user),
        );
    }

    #[Test]
    public function reset_password_with_used_recovery_code_returns_422(): void
    {
        ['user' => $user, 'codes' => $codes] = $this->userWithRecoveryCodes('rccuser@panda-estampados.com');
        $code = $codes[0];

        // Primer reset: consume el code.
        $this->postJson(uri: self::HOST . '/api/auth/reset-password', data: [
            'email' => $user->email,
            'method' => 'recovery_codes',
            'code' => $code,
            'password' => 'BrandNewPassword1234',
            'password_confirmation' => 'BrandNewPassword1234',
        ])->assertOk();

        // Segundo intento con el MISMO code: debe fallar.
        $response = $this->postJson(uri: self::HOST . '/api/auth/reset-password', data: [
            'email' => $user->email,
            'method' => 'recovery_codes',
            'code' => $code,
            'password' => 'AnotherNewPassword1234',
            'password_confirmation' => 'AnotherNewPassword1234',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function reset_password_with_recovery_code_rejects_email_only_user(): void
    {
        // admin@panda-estampados.com (creado por seeder) tiene
        // preferred_recovery_method='email' y NO tiene recovery codes.
        $response = $this->postJson(uri: self::HOST . '/api/auth/reset-password', data: [
            'email' => 'admin@panda-estampados.com',
            'method' => 'recovery_codes',
            'code' => 'AAAAAAAAAA',
            'password' => 'NewPassword1234',
            'password_confirmation' => 'NewPassword1234',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['method']);
    }

    #[Test]
    public function reset_password_email_flow_still_works_after_method_dispatch(): void
    {
        // Regression: el flow email OTP no debe romperse con la
        // integración de `method` field.
        $user = User::factory()->create(attributes: [
            'email' => 'emailuser@panda-estampados.com',
            'is_active' => true,
            'preferred_recovery_method' => 'email',
        ]);
        $store = \App\Models\Stores\Store::query()
            ->where(column: 'slug', operator: '=', value: 'panda-estampados')
            ->firstOrFail();
        $user->stores()->syncWithoutDetaching(ids: [$store->id]);

        // Step 1: forgot-password (devuelve OTP).
        $forgotResponse = $this->postJson(uri: self::HOST . '/api/auth/forgot-password', data: [
            'email' => $user->email,
        ]);
        $forgotResponse->assertOk();
        $code = $forgotResponse->json('code');
        $this->assertNotEmpty($code);

        // Step 2: reset-password sin method field (default 'email').
        $resetResponse = $this->postJson(uri: self::HOST . '/api/auth/reset-password', data: [
            'email' => $user->email,
            'code' => $code,
            'password' => 'NewPassword1234',
            'password_confirmation' => 'NewPassword1234',
        ]);

        $resetResponse->assertOk();
        $resetResponse->assertJsonStructure(['token', 'user']);
    }
}
