<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\OtpChallenge;
use App\Models\Auth\TwoFactorSetting;
use App\Models\Auth\User;
use App\Services\Auth\TotpService;
use Database\Seeders\DefaultAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integration test del flow 2FA completo vía HTTP.
 *
 * Cubre:
 *  - login con user 2FA → 200 + challenge_token (NO emite Sanctum token).
 *  - verify-totp-login con TOTP correcto → 200 + token + user.
 *  - verify-totp-login con code incorrecto → 422.
 *  - verify-totp-login con challenge expirado → 422.
 *  - verify-totp-login con challenge ya consumido → 422.
 *  - login con user SIN 2FA → 200 + token directo (sin challenge).
 *
 * Los tests usan el seeder DefaultAdminSeeder (que crea admin@
 * panda-estampados.com) y factories para crear un user con 2FA
 * activa + secret conocido.
 */
#[Group('auth')]
#[Group('http')]
#[Group('integration')]
#[Group('two-factor')]
final class TwoFactorLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://admin.panda-estampados.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DefaultAdminSeeder::class);

        // El RateLimiter usa cache shared entre tests. Limpiamos para
        // evitar que throttle residual de corridas anteriores afecte
        // los tests 2FA.
        RateLimiter::clear(key: 'forgot-password:2fauser@panda-estampados.com');
        RateLimiter::clear(key: 'forgot-password:admin@panda-estampados.com');
        RateLimiter::clear(key: 'reset-password:127.0.0.1');

        // Limpia throttle keys de PR1 (login, verify-totp-login,
        // totp-verify, recovery-codes).
        RateLimiter::clear(key: 'login:2fauser@panda-estampados.com|127.0.0.1');
        RateLimiter::clear(key: 'login:admin@panda-estampados.com|127.0.0.1');
        RateLimiter::clear(key: 'verify-totp-login:127.0.0.1');
        RateLimiter::clear(key: 'totp-verify:127.0.0.1');
        $adminUser = \App\Models\Auth\User::query()->where(column: 'email', operator: '=', value: 'admin@panda-estampados.com')->first();
        if ($adminUser !== null) {
            RateLimiter::clear(key: 'recovery-codes:' . $adminUser->id . '|127.0.0.1');
        }
    }

    /**
     * Helper: crea un user con 2FA habilitada y retorna el secret
     * base32 en texto plano para que el test compute el TOTP code.
     */
    private function userWith2fa(string $email, string $password = 'password'): array
    {
        $user = User::factory()->create(attributes: [
            'email' => $email,
            'password' => $password,
            'is_active' => true,
            'preferred_recovery_method' => '2fa',
        ]);

        // Asocia el user a la tienda panda-estampados (creada por el
        // seeder en setUp). Sin esto, el middleware store.domain +
        // el check de hasAccessToStore del AuthController rechazan
        // el login con "No tienes acceso a esta tienda".
        $store = \App\Models\Stores\Store::query()
            ->where(column: 'slug', operator: '=', value: 'panda-estampados')
            ->firstOrFail();
        $user->stores()->syncWithoutDetaching(ids: [$store->id]);

        $secret = app(abstract: TotpService::class)->generateSecret();

        TwoFactorSetting::query()->create(attributes: [
            'user_id' => $user->id,
            'secret' => Crypt::encryptString(value: $secret),
            'enabled_at' => now(),
        ]);

        return ['user' => $user, 'secret' => $secret];
    }

    /**
     * Helper: computa el código TOTP de 6 dígitos para el secret dado.
     */
    private function totpCodeFor(string $base32Secret, ?int $step = null): string
    {
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32 = strtoupper(rtrim($base32Secret, '='));
        $base32 = preg_replace('/\s+/', '', $base32) ?? $base32;
        $lookup = array_flip(str_split($base32chars));
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;
        for ($i = 0, $n = strlen($base32); $i < $n; $i++) {
            $buffer = ($buffer << 5) | $lookup[$base32[$i]];
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xff);
            }
        }
        $step ??= intdiv(num1: time(), num2: TotpService::STEP_SECONDS);
        $counter = pack('N*', 0, $step);
        $hash = hash_hmac(algo: 'sha1', data: $counter, key: $output, binary: true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        ) % (10 ** TotpService::DIGITS);

        return str_pad(
            string: (string) $code,
            length: TotpService::DIGITS,
            pad_string: '0',
            pad_type: STR_PAD_LEFT,
        );
    }

    #[Test]
    public function login_with_2fa_returns_challenge_token(): void
    {
        ['user' => $user] = $this->userWith2fa('2fauser@panda-estampados.com');

        $response = $this->postJson(uri: self::HOST . '/api/auth/login', data: [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'integration-test',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['status', 'challenge_token', 'method', 'expires_at']);
        $response->assertJsonPath(path: 'status', expect: '2fa_required');
        $response->assertJsonPath(path: 'method', expect: 'totp');

        // NO debe emitir token todavía.
        $response->assertJsonMissingPath('token');

        // El challenge debe existir en DB.
        $this->assertDatabaseHas(table: 'otp_challenges', data: [
            'user_id' => $user->id,
            'type' => OtpChallenge::TYPE_TOTP,
            'consumed_at' => null,
        ]);
    }

    #[Test]
    public function verify_totp_login_with_correct_code_returns_token(): void
    {
        ['user' => $user, 'secret' => $secret] = $this->userWith2fa('2fauser@panda-estampados.com');

        $loginResponse = $this->postJson(uri: self::HOST . '/api/auth/login', data: [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'integration-test',
        ]);
        $challengeToken = (int) $loginResponse->json('challenge_token');

        $verifyResponse = $this->postJson(uri: self::HOST . '/api/auth/2fa/verify-totp-login', data: [
            'challenge_token' => $challengeToken,
            'code' => $this->totpCodeFor($secret),
        ]);

        $verifyResponse->assertOk();
        $verifyResponse->assertJsonStructure(['token', 'user' => ['id', 'email']]);
        $verifyResponse->assertJsonPath(path: 'user.email', expect: $user->email);

        // Challenge consumido.
        $this->assertDatabaseHas(table: 'otp_challenges', data: [
            'id' => $challengeToken,
            'consumed_at' => now()->toDateTimeString(),
        ]);
    }

    #[Test]
    public function verify_totp_login_with_wrong_code_returns_422(): void
    {
        ['user' => $user] = $this->userWith2fa('2fauser@panda-estampados.com');

        $loginResponse = $this->postJson(uri: self::HOST . '/api/auth/login', data: [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $challengeToken = (int) $loginResponse->json('challenge_token');

        $verifyResponse = $this->postJson(uri: self::HOST . '/api/auth/2fa/verify-totp-login', data: [
            'challenge_token' => $challengeToken,
            'code' => '000000', // código deliberadamente incorrecto
        ]);

        $verifyResponse->assertStatus(422);
        $verifyResponse->assertJsonPath(path: 'message', expect: 'Código inválido.');

        // Challenge NO consumido, attempts incrementado.
        $challenge = OtpChallenge::query()->find(id: $challengeToken);
        $this->assertNotNull($challenge);
        $this->assertNull($challenge->consumed_at);
        $this->assertSame(expected: 1, actual: $challenge->attempts);
    }

    #[Test]
    public function verify_totp_login_with_expired_challenge_returns_422(): void
    {
        ['user' => $user] = $this->userWith2fa('2fauser@panda-estampados.com');

        $loginResponse = $this->postJson(uri: self::HOST . '/api/auth/login', data: [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $challengeToken = (int) $loginResponse->json('challenge_token');

        // Forzar expiración: actualizar expires_at a 10 minutos atrás.
        OtpChallenge::query()->where('id', $challengeToken)->update([
            'expires_at' => now()->subMinutes(10),
        ]);

        $verifyResponse = $this->postJson(uri: self::HOST . '/api/auth/2fa/verify-totp-login', data: [
            'challenge_token' => $challengeToken,
            'code' => '123456',
        ]);

        $verifyResponse->assertStatus(422);
        $verifyResponse->assertJsonPath(path: 'message', expect: 'Challenge inválido o expirado.');
    }

    #[Test]
    public function verify_totp_login_after_consuming_challenge_returns_422(): void
    {
        ['user' => $user, 'secret' => $secret] = $this->userWith2fa('2fauser@panda-estampados.com');

        $loginResponse = $this->postJson(uri: self::HOST . '/api/auth/login', data: [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $challengeToken = (int) $loginResponse->json('challenge_token');

        // Primer verify consume el challenge.
        $this->postJson(uri: self::HOST . '/api/auth/2fa/verify-totp-login', data: [
            'challenge_token' => $challengeToken,
            'code' => $this->totpCodeFor($secret),
        ])->assertOk();

        // Segundo verify con el MISMO challenge debe fallar.
        $secondVerify = $this->postJson(uri: self::HOST . '/api/auth/2fa/verify-totp-login', data: [
            'challenge_token' => $challengeToken,
            'code' => $this->totpCodeFor($secret),
        ]);

        $secondVerify->assertStatus(422);
        $secondVerify->assertJsonPath(path: 'message', expect: 'Challenge inválido o expirado.');
    }

    #[Test]
    public function login_without_2fa_returns_token_directly(): void
    {
        // admin@panda-estampados.com es creado por DefaultAdminSeeder SIN 2FA.
        $response = $this->postJson(uri: self::HOST . '/api/auth/login', data: [
            'email' => 'admin@panda-estampados.com',
            'password' => 'password',
            'device_name' => 'integration-test',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'user' => ['id', 'email']]);
        $response->assertJsonPath(path: 'user.email', expect: 'admin@panda-estampados.com');

        // NO devuelve challenge.
        $response->assertJsonMissingPath('challenge_token');
    }

    #[Test]
    public function it_blocks_verify_totp_login_after_10_attempts_per_ip(): void
    {
        // 10 intentos de verify-totp-login desde la misma IP (mezcla de
        // códigos correctos e incorrectos): el 11º debe ser bloqueado.
        ['user' => $user, 'secret' => $secret] = $this->userWith2fa('2fauser@panda-estampados.com');

        // Generamos 11 challenges (uno por intento). Cada verify consume
        // o incrementa attempts; algunos intentos serán 422 por código
        // incorrecto o challenge consumido, pero todos cuentan para el
        // throttle IP-based.
        for ($i = 0; $i < 10; $i++) {
            $loginResponse = $this->postJson(uri: self::HOST . '/api/auth/login', data: [
                'email' => $user->email,
                'password' => 'password',
            ]);
            $challengeToken = (int) $loginResponse->json('challenge_token');

            $verifyResponse = $this->postJson(uri: self::HOST . '/api/auth/2fa/verify-totp-login', data: [
                'challenge_token' => $challengeToken,
                'code' => $i % 2 === 0 ? '000000' : $this->totpCodeFor($secret),
            ]);

            // Primer intento con code correcto: success. Resto: 422.
            // Todos cuentan para el throttle.
            if ($i === 0) {
                // 1º: code incorrecto → 422.
                $this->assertSame(expected: 422, actual: $verifyResponse->status());
            } elseif ($i === 1) {
                // 2º: code correcto → 200.
                $verifyResponse->assertOk();
            } else {
                // 3-10: alternan. Algunos pueden ser 200 (challenge_token
                // nuevos con codes correctos), otros 422. Lo importante
                // es que NINGUNO devuelve 429.
                $this->assertContains(needle: $verifyResponse->status(), haystack: [200, 422]);
            }
        }

        // 11º intento: nuevo login → challenge, pero verify debe ser bloqueado.
        $loginResponse = $this->postJson(uri: self::HOST . '/api/auth/login', data: [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $challengeToken = (int) $loginResponse->json('challenge_token');

        $verifyResponse = $this->postJson(uri: self::HOST . '/api/auth/2fa/verify-totp-login', data: [
            'challenge_token' => $challengeToken,
            'code' => $this->totpCodeFor($secret),
        ]);

        $verifyResponse->assertStatus(422);
        $verifyResponse->assertJsonPath(
            path: 'errors.code.0',
            expect: fn (string $m): bool => str_contains($m, 'Demasiados intentos'),
        );
    }
}
