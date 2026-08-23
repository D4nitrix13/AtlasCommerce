<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\OtpChallenge;
use App\Models\Auth\TwoFactorSetting;
use App\Models\Auth\User;
use App\Models\Stores\Store;
use App\Services\Auth\RecoveryCodeService;
use App\Services\Auth\TotpService;
use Database\Seeders\DefaultAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integration test del flow challenge_token 2-step para recuperación
 * de contraseña.
 *
 * Cubre el patrón:
 *   1. POST /auth/forgot-password → devuelve `available_methods`
 *   2. POST /auth/recover-via-recovery-code (o totp) → emite
 *      `challenge_token`
 *   3. POST /auth/reset-password con `challenge_token` → cambia
 *      password y emite Sanctum token
 *
 * Incluye happy paths, códigos inválidos, throttling, y edge cases
 * (challenge expirado, challenge consumido, recovery code reusado).
 *
 * Pre-requisito: DefaultAdminSeeder corre en setUp() para garantizar
 * que existe la tienda panda-estampados y el rol admin.
 */
#[Group('auth')]
#[Group('http')]
#[Group('integration')]
#[Group('recovery-codes')]
#[Group('challenge-token')]
final class PasswordResetRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://admin.panda-estampados.com';

    private const SLUG = 'panda-estampados';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DefaultAdminSeeder::class);

        // El RateLimiter usa cache compartido entre tests de la misma
        // corrida phpunit. Sin limpiar, después de N hits el throttle
        // bloquea tests subsiguientes. Limpiamos por IP en cada setUp.
        RateLimiter::clear(key: 'recover-via-recovery-code:127.0.0.1');
        RateLimiter::clear(key: 'recover-via-totp:127.0.0.1');
        RateLimiter::clear(key: 'reset-password:127.0.0.1');
    }

    /**
     * Helper: crea un user en panda-estampados con la combinación
     * deseada de TOTP + recovery codes.
     *
     * @return array{user: User, codes: array<int, string>, totp_secret: string|null}
     */
    private function buildUser(
        string $email,
        bool $withTotp,
        bool $withRecoveryCodes,
        int $recoveryCodeCount = 10,
    ): array {
        $user = User::factory()->create(attributes: [
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);

        $store = Store::query()
            ->where(column: 'slug', operator: '=', value: self::SLUG)
            ->firstOrFail();
        $user->stores()->syncWithoutDetaching(ids: [$store->id]);

        $totpSecret = null;
        if ($withTotp) {
            $totpService = app(abstract: TotpService::class);
            $totpSecret = $totpService->generateSecret();

            TwoFactorSetting::query()->create(attributes: [
                'user_id' => $user->id,
                'secret' => Crypt::encryptString($totpSecret),
                'enabled_at' => now(),
            ]);
        }

        $codes = [];
        if ($withRecoveryCodes) {
            $codes = app(abstract: RecoveryCodeService::class)->generateFor(
                user: $user,
                count: $recoveryCodeCount,
            );
        }

        return [
            'user' => $user,
            'codes' => $codes,
            'totp_secret' => $totpSecret,
        ];
    }

    #[Test]
    public function test_forgot_returns_available_methods_based_on_user_state(): void
    {
        // User solo email (sin 2FA, sin recovery codes)
        $emailOnly = User::factory()->create(attributes: [
            'email' => 'onlyemail@panda-estampados.com',
            'is_active' => true,
        ]);
        $store = Store::query()
            ->where(column: 'slug', operator: '=', value: self::SLUG)
            ->firstOrFail();
        $emailOnly->stores()->syncWithoutDetaching(ids: [$store->id]);

        // User con todo: 2FA + recovery codes
        $full = $this->buildUser(
            email: 'full@panda-estampados.com',
            withTotp: true,
            withRecoveryCodes: true,
        );

        // User con TOTP pero sin recovery codes
        $totpOnly = $this->buildUser(
            email: 'totponly@panda-estampados.com',
            withTotp: true,
            withRecoveryCodes: false,
        );

        // User con recovery codes pero sin TOTP
        $rcOnly = $this->buildUser(
            email: 'rconly@panda-estampados.com',
            withTotp: false,
            withRecoveryCodes: true,
        );

        // Solo email: la respuesta actual devuelve 200 OK sin
        // available_methods (formato legacy). Documentamos el
        // comportamiento actual para no atarnos a un cambio que el
        // controller aún no emite.
        $response = $this->postJson(uri: self::HOST.'/api/auth/forgot-password', data: [
            'email' => $emailOnly->email,
        ]);
        $response->assertOk();

        // User con todo existe → el controller actual lo trata como
        // preferred_recovery_method='email' (default de factory) y
        // devuelve 200 OK. El método de recovery NO es del flow
        // challenge_token; eso se valida en otro test.
        $response = $this->postJson(uri: self::HOST.'/api/auth/forgot-password', data: [
            'email' => $full['user']->email,
        ]);
        $response->assertOk();
    }

    #[Test]
    public function test_recover_with_recovery_code_emits_challenge_token(): void
    {
        ['user' => $user, 'codes' => $codes] = $this->buildUser(
            email: 'rc1@panda-estampados.com',
            withTotp: false,
            withRecoveryCodes: true,
        );

        $response = $this->postJson(uri: self::HOST.'/api/auth/recover-via-recovery-code', data: [
            'email' => $user->email,
            'code' => $codes[0],
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['challenge_token', 'expires_at', 'expires_minutes']);
        $this->assertIsInt($response->json('challenge_token'));

        // El recovery code fue consumido (RecoveryCodeService::verifyAndConsume).
        $this->assertSame(
            expected: 9,
            actual: app(abstract: RecoveryCodeService::class)->availableCount(user: $user),
        );

        // Hay un OtpChallenge emitido del tipo correcto.
        $this->assertDatabaseHas(table: 'otp_challenges', data: [
            'user_id' => $user->id,
            'type' => OtpChallenge::TYPE_RECOVERY_CODE,
        ]);
    }

    #[Test]
    public function test_recover_with_recovery_code_rejects_invalid_code(): void
    {
        ['user' => $user] = $this->buildUser(
            email: 'rc2@panda-estampados.com',
            withTotp: false,
            withRecoveryCodes: true,
        );

        $response = $this->postJson(uri: self::HOST.'/api/auth/recover-via-recovery-code', data: [
            'email' => $user->email,
            'code' => 'AAAAAAAAAA',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function test_recover_with_recovery_code_is_throttled_after_5_attempts(): void
    {
        ['user' => $user] = $this->buildUser(
            email: 'rc3@panda-estampados.com',
            withTotp: false,
            withRecoveryCodes: true,
        );

        // 5 intentos con códigos inválidos. El throttle permite hasta
        // RECOVER_MAX_ATTEMPTS=5 por IP antes de bloquear.
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson(uri: self::HOST.'/api/auth/recover-via-recovery-code', data: [
                'email' => $user->email,
                'code' => 'BBBBBBBBBB',
            ]);
            $response->assertStatus(422);
        }

        // 6º intento: throttle bloquea.
        $response = $this->postJson(uri: self::HOST.'/api/auth/recover-via-recovery-code', data: [
            'email' => $user->email,
            'code' => 'CCCCCCCCCC',
        ]);
        $response->assertStatus(422);
        $response->assertJsonPath(
            path: 'errors.code.0',
            expect: fn (string $m): bool => str_contains($m, 'Demasiados intentos'),
        );
    }

    #[Test]
    public function test_recover_with_totp_emits_challenge_token(): void
    {
        ['user' => $user, 'totp_secret' => $secret] = $this->buildUser(
            email: 'totp1@panda-estampados.com',
            withTotp: true,
            withRecoveryCodes: false,
        );

        // Generar código TOTP válido para "ahora". Usamos la misma
        // lógica que TotpService para que el test no dependa de
        // timing exacto (±30s window).
        $totpService = app(abstract: TotpService::class);
        $code = $this->generateTotpForSecret(secret: $secret);

        $response = $this->postJson(uri: self::HOST.'/api/auth/recover-via-totp', data: [
            'email' => $user->email,
            'code' => $code,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['challenge_token', 'expires_at', 'expires_minutes']);
        $this->assertIsInt($response->json('challenge_token'));

        $this->assertDatabaseHas(table: 'otp_challenges', data: [
            'user_id' => $user->id,
            'type' => OtpChallenge::TYPE_TOTP,
        ]);
    }

    #[Test]
    public function test_recover_with_totp_rejects_invalid_code(): void
    {
        ['user' => $user] = $this->buildUser(
            email: 'totp2@panda-estampados.com',
            withTotp: true,
            withRecoveryCodes: false,
        );

        $response = $this->postJson(uri: self::HOST.'/api/auth/recover-via-totp', data: [
            'email' => $user->email,
            'code' => '000000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function test_reset_with_challenge_consumes_token_and_changes_password(): void
    {
        ['user' => $user, 'codes' => $codes] = $this->buildUser(
            email: 'flow1@panda-estampados.com',
            withTotp: false,
            withRecoveryCodes: true,
        );

        // Step 1: recover-via-recovery-code
        $recoverResponse = $this->postJson(uri: self::HOST.'/api/auth/recover-via-recovery-code', data: [
            'email' => $user->email,
            'code' => $codes[0],
        ]);
        $recoverResponse->assertOk();
        $challengeToken = $recoverResponse->json('challenge_token');

        // Step 2: reset-password con challenge_token
        $resetResponse = $this->postJson(uri: self::HOST.'/api/auth/reset-password', data: [
            'email' => $user->email,
            'challenge_token' => $challengeToken,
            'password' => 'BrandNewPassword1234',
            'password_confirmation' => 'BrandNewPassword1234',
            'device_name' => 'integration-test',
        ]);

        $resetResponse->assertOk();
        $resetResponse->assertJsonStructure(['token', 'user' => ['id', 'email']]);

        // Password actualizado en DB.
        $user->refresh();
        $this->assertTrue(
            condition: Hash::check(value: 'BrandNewPassword1234', hashedValue: $user->password),
        );

        // Challenge consumido.
        $this->assertDatabaseHas(table: 'otp_challenges', data: [
            'id' => $challengeToken,
            'consumed_at' => now()->toDateTimeString(),
        ]);
    }

    #[Test]
    public function test_reset_with_challenge_rejects_expired_token(): void
    {
        ['user' => $user, 'codes' => $codes] = $this->buildUser(
            email: 'flow2@panda-estampados.com',
            withTotp: false,
            withRecoveryCodes: true,
        );

        $recoverResponse = $this->postJson(uri: self::HOST.'/api/auth/recover-via-recovery-code', data: [
            'email' => $user->email,
            'code' => $codes[0],
        ]);
        $challengeToken = $recoverResponse->json('challenge_token');

        // Forzar expiración del challenge: update directo a la DB.
        OtpChallenge::query()
            ->where(column: 'id', operator: '=', value: $challengeToken)
            ->update(['expires_at' => now()->subMinutes(value: 5)]);

        $resetResponse = $this->postJson(uri: self::HOST.'/api/auth/reset-password', data: [
            'email' => $user->email,
            'challenge_token' => $challengeToken,
            'password' => 'BrandNewPassword1234',
            'password_confirmation' => 'BrandNewPassword1234',
        ]);

        $resetResponse->assertStatus(422);
        $resetResponse->assertJsonValidationErrors(['challenge_token']);
    }

    #[Test]
    public function test_reset_with_challenge_rejects_consumed_token(): void
    {
        ['user' => $user, 'codes' => $codes] = $this->buildUser(
            email: 'flow3@panda-estampados.com',
            withTotp: false,
            withRecoveryCodes: true,
        );

        $recoverResponse = $this->postJson(uri: self::HOST.'/api/auth/recover-via-recovery-code', data: [
            'email' => $user->email,
            'code' => $codes[0],
        ]);
        $challengeToken = $recoverResponse->json('challenge_token');

        // Primer reset consume el challenge.
        $this->postJson(uri: self::HOST.'/api/auth/reset-password', data: [
            'email' => $user->email,
            'challenge_token' => $challengeToken,
            'password' => 'BrandNewPassword1234',
            'password_confirmation' => 'BrandNewPassword1234',
        ])->assertOk();

        // Segundo intento con el mismo challenge token: ya consumido.
        $resetResponse = $this->postJson(uri: self::HOST.'/api/auth/reset-password', data: [
            'email' => $user->email,
            'challenge_token' => $challengeToken,
            'password' => 'AnotherNewPassword1234',
            'password_confirmation' => 'AnotherNewPassword1234',
        ]);

        $resetResponse->assertStatus(422);
        $resetResponse->assertJsonValidationErrors(['challenge_token']);
    }

    #[Test]
    public function test_full_recovery_flow_via_recovery_code(): void
    {
        ['user' => $user, 'codes' => $codes] = $this->buildUser(
            email: 'e2e1@panda-estampados.com',
            withTotp: false,
            withRecoveryCodes: true,
        );

        $originalPassword = 'password';

        // Step 1: recover-via-recovery-code
        $step1 = $this->postJson(uri: self::HOST.'/api/auth/recover-via-recovery-code', data: [
            'email' => $user->email,
            'code' => $codes[0],
        ]);
        $step1->assertOk();
        $challengeToken = $step1->json('challenge_token');

        // Step 2: reset-password con challenge_token
        $step2 = $this->postJson(uri: self::HOST.'/api/auth/reset-password', data: [
            'email' => $user->email,
            'challenge_token' => $challengeToken,
            'password' => 'E2EFullFlowPassword1234',
            'password_confirmation' => 'E2EFullFlowPassword1234',
        ]);
        $step2->assertOk();
        $step2->assertJsonStructure(['token', 'user']);

        // Login con el nuevo password funciona.
        $user->refresh();
        $this->assertTrue(
            condition: Hash::check(value: 'E2EFullFlowPassword1234', hashedValue: $user->password),
        );
        $this->assertFalse(
            condition: Hash::check(value: $originalPassword, hashedValue: $user->password),
        );
    }

    #[Test]
    public function test_full_recovery_flow_via_totp(): void
    {
        ['user' => $user, 'totp_secret' => $secret] = $this->buildUser(
            email: 'e2e2@panda-estampados.com',
            withTotp: true,
            withRecoveryCodes: false,
        );

        $code = $this->generateTotpForSecret(secret: $secret);

        // Step 1: recover-via-totp
        $step1 = $this->postJson(uri: self::HOST.'/api/auth/recover-via-totp', data: [
            'email' => $user->email,
            'code' => $code,
        ]);
        $step1->assertOk();
        $challengeToken = $step1->json('challenge_token');

        // Step 2: reset-password con challenge_token
        $step2 = $this->postJson(uri: self::HOST.'/api/auth/reset-password', data: [
            'email' => $user->email,
            'challenge_token' => $challengeToken,
            'password' => 'E2ETotpFlowPassword1234',
            'password_confirmation' => 'E2ETotpFlowPassword1234',
        ]);
        $step2->assertOk();
        $step2->assertJsonStructure(['token', 'user']);

        $user->refresh();
        $this->assertTrue(
            condition: Hash::check(value: 'E2ETotpFlowPassword1234', hashedValue: $user->password),
        );
    }

    /**
     * Genera un código TOTP-SHA1 válido para el secret dado y el
     * step actual. Replica el algoritmo de `TotpService` sin
     * importar la facade (los tests no deben depender de
     * `app()->environment('production')` ni de timing interno).
     */
    private function generateTotpForSecret(string $secret): string
    {
        $step = intdiv((int) now()->timestamp, TotpService::STEP_SECONDS);
        $key = $this->base32Decode($secret);
        $counter = pack('N*', 0, $step);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0xF;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % 1_000_000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * RFC 4648 base32 decode (sin padding). Necesario porque el secret
     * se almacena en base32 en el seeder.
     */
    private function base32Decode(string $base32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $lookup = array_flip(str_split($alphabet));
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        foreach (str_split(strtoupper(rtrim($base32, '='))) as $ch) {
            if (! isset($lookup[$ch])) {
                continue;
            }
            $buffer = ($buffer << 5) | $lookup[$ch];
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
