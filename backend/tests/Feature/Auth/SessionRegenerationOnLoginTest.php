<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\Role;
use App\Models\Auth\TwoFactorSetting;
use App\Models\Auth\User;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integration test del comportamiento anti session-fixation en login.
 *
 * Session fixation es un ataque donde el atacante fuerza a la víctima
 * a usar un session ID conocido antes del login (ej. via XSS, link
 * malicioso con cookie pre-set, o un subdominio comprometido). Después
 * del login, el atacante presenta ese mismo session ID y queda
 * autenticado como la víctima.
 *
 * Defensa: `regenerate()` rota el session ID al momento de la
 * autenticación. La sesión anterior queda invalidada y el cookie
 * emitido al user tiene un nuevo ID que el atacante no conoce.
 *
 * Cubre:
 *   1. POST /auth/login rota el session ID (la cookie de sesión
 *      emitida tiene un session ID distinto al pre-login).
 *   2. POST /auth/2fa/verify-totp-login rota el session ID por la
 *      misma razón: el challenge 2FA ocurre post-login, pero el
 *      atacante podría conocer el session ID pre-login.
 *
 * Para que la sesión se persista entre requests del mismo test, se
 * fuerza `session.driver = 'file'` (phpunit.xml usa `array` per-request).
 */
#[Group('auth')]
#[Group('security')]
#[Group('session-fixation')]
final class SessionRegenerationOnLoginTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'panda-estampados.test';

    private const USER_EMAIL = 'admin@panda-estampados.test';

    private const USER_PASSWORD = 'secret-password';

    private const SESSION_DIR_NAME = 'session-regen-test-sessions';

    private const SESSION_DIR_PERMS = 0777;

    private User $user;

    private string $sessionCookieName;

    protected function setUp(): void
    {
        parent::setUp();

        // Forzar session driver = 'file' para que persista entre
        // requests del mismo test (phpunit.xml default = 'array'
        // per-request, lo que imposibilita comparar session IDs).
        $sessionDir = sys_get_temp_dir().'/'.self::SESSION_DIR_NAME;
        if (! is_dir($sessionDir)) {
            mkdir($sessionDir, self::SESSION_DIR_PERMS, true);
        }
        config(['session.driver' => 'file', 'session.files' => $sessionDir]);
        $this->sessionCookieName = config(key: 'session.cookie');

        $role = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );

        $currency = Currency::factory()->primary()->create();
        $store = Store::factory()->for($currency, 'currency')->create();

        StoreDomain::query()->create(attributes: [
            'store_id' => $store->id,
            'domain' => self::HOST,
            'type' => 'domain',
            'base_path' => '/',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $this->user = User::factory()->create(attributes: [
            'email' => self::USER_EMAIL,
            'password' => Hash::make(value: self::USER_PASSWORD),
            'is_active' => true,
            'role_id' => $role->id,
        ]);
        $this->user->stores()->attach($store->id);

        // Forzar hosts stateful para que EnsureFrontendRequestsAreStateful
        // active autenticación por sesión.
        config(['sanctum.stateful' => [self::HOST]]);

        RateLimiter::clear(key: 'login:'.self::USER_EMAIL.'|127.0.0.1');
        RateLimiter::clear(key: 'verify-totp-login:127.0.0.1');
    }

    protected function tearDown(): void
    {
        $dir = sys_get_temp_dir().'/'.self::SESSION_DIR_NAME;
        if (is_dir($dir)) {
            array_map(unlink(...), glob($dir.'/*') ?: []);
            @rmdir($dir);
        }

        parent::tearDown();
    }

    /**
     * Hit /sanctum/csrf-cookie y devolver todas las cookies emitidas.
     *
     * Este endpoint crea una sesión pre-login y emite XSRF-TOKEN +
     * session cookie. Lo usamos para capturar el session ID pre-login.
     *
     * @return array<string, string>
     */
    private function bootstrapCsrf(): array
    {
        $response = $this->withHeaders([
            'Origin' => 'http://'.self::HOST,
        ])->get('http://'.self::HOST.'/sanctum/csrf-cookie');

        $cookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        return $cookies;
    }

    #[Test]
    public function login_rotates_session_id_to_prevent_session_fixation(): void
    {
        $bootstrap = $this->bootstrapCsrf();
        $preLoginSessionId = $bootstrap[$this->sessionCookieName] ?? null;

        $response = $this->withHeaders([
            'Origin' => 'http://'.self::HOST,
            'X-XSRF-TOKEN' => $bootstrap['XSRF-TOKEN'] ?? '',
        ])->postJson('http://'.self::HOST.'/api/v1/auth/login', [
            'email' => self::USER_EMAIL,
            'password' => self::USER_PASSWORD,
            'device_name' => 'session-fixation-test',
        ]);

        $response->assertOk();

        $postLoginSessionId = null;
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $this->sessionCookieName) {
                $postLoginSessionId = $cookie->getValue();
                break;
            }
        }

        $this->assertNotNull($postLoginSessionId, 'Login debe emitir session cookie.');
        $this->assertNotSame(
            expected: $preLoginSessionId,
            actual: $postLoginSessionId,
            message: 'Session ID debe rotar después de login (anti session-fixation).',
        );
    }

    #[Test]
    public function verify_totp_login_rotates_session_id_to_prevent_session_fixation(): void
    {
        $secret = app(abstract: TotpService::class)->generateSecret();
        TwoFactorSetting::query()->create(attributes: [
            'user_id' => $this->user->id,
            'secret' => Crypt::encryptString(value: $secret),
            'enabled_at' => now(),
        ]);

        $bootstrap = $this->bootstrapCsrf();

        $loginResponse = $this->withHeaders([
            'Origin' => 'http://'.self::HOST,
            'X-XSRF-TOKEN' => $bootstrap['XSRF-TOKEN'] ?? '',
        ])->postJson('http://'.self::HOST.'/api/v1/auth/login', [
            'email' => self::USER_EMAIL,
            'password' => self::USER_PASSWORD,
        ]);
        $loginResponse->assertOk();
        $challengeToken = (int) $loginResponse->json('challenge_token');

        $preVerifySessionId = null;
        foreach ($loginResponse->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $this->sessionCookieName) {
                $preVerifySessionId = $cookie->getValue();
                break;
            }
        }
        $this->assertNotNull(
            $preVerifySessionId,
            'Login con 2FA debe emitir session cookie pre-verify.',
        );

        $verifyResponse = $this->withHeaders([
            'Origin' => 'http://'.self::HOST,
            'X-XSRF-TOKEN' => $bootstrap['XSRF-TOKEN'] ?? '',
        ])->postJson('http://'.self::HOST.'/api/v1/auth/2fa/verify-totp-login', [
            'challenge_token' => $challengeToken,
            'code' => $this->totpCodeFor($secret),
        ]);

        $verifyResponse->assertOk();

        $postVerifySessionId = null;
        foreach ($verifyResponse->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $this->sessionCookieName) {
                $postVerifySessionId = $cookie->getValue();
                break;
            }
        }

        $this->assertNotNull($postVerifySessionId, 'verifyTotpLogin debe emitir session cookie.');
        $this->assertNotSame(
            expected: $preVerifySessionId,
            actual: $postVerifySessionId,
            message: 'Session ID debe rotar después de verifyTotpLogin (anti session-fixation).',
        );
    }

    /**
     * Helper: computa el código TOTP de 6 dígitos para el secret dado.
     * Replica el algoritmo RFC 6238 de TotpService::generateCodeAtStep.
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
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        $step ??= intdiv(num1: time(), num2: TotpService::STEP_SECONDS);
        $counter = pack('N*', 0, $step);
        $hash = hash_hmac(algo: 'sha1', data: $counter, key: $output, binary: true);
        $offset = ord($hash[19]) & 0xF;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | ((ord($hash[$offset + 3]) & 0xFF))
        ) % (10 ** TotpService::DIGITS);

        return str_pad(
            string: (string) $code,
            length: TotpService::DIGITS,
            pad_string: '0',
            pad_type: STR_PAD_LEFT,
        );
    }
}
