<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Models\Auth\User;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests para TotpService.
 *
 * Los tests validan:
 *  1. generateSecret() retorna 32 chars base32 (52 chars sin padding).
 *  2. generateSecret() retorna strings únicos en cada llamada.
 *  3. verify() retorna false si el user no tiene 2FA habilitada.
 *  4. verify() retorna true para un code generado en el step actual.
 *  5. verify() retorna true para codes con offset -1 y +1 (±30s).
 *  6. verify() retorna false para codes fuera de la ventana.
 *  7. verify() rechaza codes con formato inválido (no numérico, etc.).
 *  8. provisioningUri() genera URL otpauth:// válida.
 */
final class TotpServiceTest extends TestCase
{
    use RefreshDatabase;

    private TotpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TotpService();
    }

    public function test_generate_secret_returns_base32_string(): void
    {
        $secret = $this->service->generateSecret();
        $this->assertSame(52, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function test_generate_secret_returns_unique_values(): void
    {
        $a = $this->service->generateSecret();
        $b = $this->service->generateSecret();
        $this->assertNotSame($a, $b);
    }

    public function test_verify_returns_false_when_2fa_not_configured(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($this->service->verify($user, '123456'));
    }

    public function test_verify_returns_true_for_current_code(): void
    {
        $user = $this->factoryUserWith2fa();

        $secret = app(\Illuminate\Contracts\Encryption\Encrypter::class)
            ->decrypt($user->twoFactorSetting->secret);
        $step = intdiv(time(), TotpService::STEP_SECONDS);
        $code = $this->generateCodeForStep($secret, $step);

        $this->assertTrue($this->service->verify($user, $code));
    }

    public function test_verify_returns_true_for_previous_step(): void
    {
        $user = $this->factoryUserWith2fa();
        $secret = app(\Illuminate\Contracts\Encryption\Encrypter::class)
            ->decrypt($user->twoFactorSetting->secret);
        $step = intdiv(time(), TotpService::STEP_SECONDS) - 1;
        $code = $this->generateCodeForStep($secret, $step);

        $this->assertTrue($this->service->verify($user, $code));
    }

    public function test_verify_returns_true_for_next_step(): void
    {
        $user = $this->factoryUserWith2fa();
        $secret = app(\Illuminate\Contracts\Encryption\Encrypter::class)
            ->decrypt($user->twoFactorSetting->secret);
        $step = intdiv(time(), TotpService::STEP_SECONDS) + 1;
        $code = $this->generateCodeForStep($secret, $step);

        $this->assertTrue($this->service->verify($user, $code));
    }

    public function test_verify_returns_false_for_code_outside_window(): void
    {
        $user = $this->factoryUserWith2fa();
        $secret = app(\Illuminate\Contracts\Encryption\Encrypter::class)
            ->decrypt($user->twoFactorSetting->secret);
        $step = intdiv(time(), TotpService::STEP_SECONDS) - 5;
        $code = $this->generateCodeForStep($secret, $step);

        $this->assertFalse($this->service->verify($user, $code));
    }

    public function test_verify_rejects_non_numeric_code(): void
    {
        $user = $this->factoryUserWith2fa();
        $this->assertFalse($this->service->verify($user, 'abcdef'));
        $this->assertFalse($this->service->verify($user, '12345'));
        $this->assertFalse($this->service->verify($user, '1234567'));
        $this->assertFalse($this->service->verify($user, '12 34 56'));
    }

    public function test_provisioning_uri_format(): void
    {
        $user = $this->factoryUserWith2fa();
        $uri = $this->service->provisioningUri($user, $user->twoFactorSetting->secret);
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString(rawurlencode($user->email), $uri);
        $this->assertStringContainsString('issuer=', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
        $this->assertStringContainsString('algorithm=SHA1', $uri);
    }

    /**
     * Helper: genera un user con 2FA habilitada.
     */
    private function factoryUserWith2fa(): User
    {
        $user = User::factory()->create();
        $user->twoFactorSetting()->create([
            'secret' => app(\Illuminate\Contracts\Encryption\Encrypter::class)
                ->encrypt($this->service->generateSecret()),
            'enabled_at' => now(),
        ]);
        $user->refresh();
        return $user;
    }

    /**
     * Helper: genera el código TOTP para un step dado (mismo algoritmo
     * que TotpService internamente, para que el test sea independiente).
     */
    private function generateCodeForStep(string $base32Secret, int $step): string
    {
        $key = $this->base32Decode($base32Secret);
        $counter = pack('N*', 0, $step);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        ) % (10 ** TotpService::DIGITS);
        return str_pad((string) $code, TotpService::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $base32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $lookup = array_flip(str_split($alphabet));
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
        return $output;
    }
}
